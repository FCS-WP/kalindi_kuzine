<?php

namespace AiZippy\Checkout;

defined('ABSPATH') || exit;

/**
 * Handle distance-based shipping calculation at checkout.
 */
class CheckoutShipping
{
    public static function register(): void
    {
        add_action('woocommerce_checkout_update_order_review', [self::class, 'calculateDistanceClassic'], 10);
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'calculateDistance'], 10, 2);
        add_filter('woocommerce_package_rates', [self::class, 'addDistanceToLabels'], 100, 2);
        add_filter('woocommerce_store_api_cart_response', [self::class, 'extendCartResponse'], 10, 2);
        add_action('woocommerce_after_calculate_totals', [self::class, 'syncShippingTotalToCart'], 100);
        add_filter('woocommerce_checkout_fields', [self::class, 'removeAddressFields']);
        add_action('woocommerce_checkout_create_order', [self::class, 'populateOrderAddressFromSession'], 10, 2);

        // Ensure address and selection are synced during checkout flow
        add_action('woocommerce_checkout_init', [self::class, 'ensureAddressAndSelection']);
        add_action('woocommerce_checkout_update_order_review', [self::class, 'ensureAddressAndSelection'], 5);
        add_action('woocommerce_checkout_process', [self::class, 'ensureAddressAndSelection']);
    }

    /**
     * Check if a product belongs to 'party-order' or its subcategories.
     */
    public static function isPartyOrderProduct(int $product_id): bool
    {
        $term = get_term_by('slug', 'party-order', 'product_cat');
        if (!$term) return false;

        $child_ids = get_term_children($term->term_id, 'product_cat');
        $all_ids = array_merge([$term->term_id], (array)$child_ids);

        return has_term($all_ids, 'product_cat', $product_id);
    }

    /**
     * Check if the current cart has any party order products.
     */
    public static function hasPartyOrderInCart(): bool
    {
        if (!WC()->cart) return false;

        foreach (WC()->cart->get_cart() as $item) {
            if (self::isPartyOrderProduct($item['product_id'])) {
                return true;
            }
        }
        return false;
    }

    public static function extendCartResponse($response, $cart): array
    {
        $distance_meters = WC()->session ? (float) WC()->session->get('total_distance') : 0;
        $distance_km = round($distance_meters / 1000, 2);

        $response['extensions']['zippy_booking'] = [
            'distance_km' => $distance_km,
            'has_party_order' => self::hasPartyOrderInCart()
        ];
        return $response;
    }

    /**
     * For Classic Checkout AJAX updates.
     */
    public static function calculateDistanceClassic($post_data): void
    {
        if (!WC()->session) return;

        // If post_data is passed as a string (serialized), parse it
        $parsed_data = [];
        if (is_string($post_data)) {
            parse_str($post_data, $parsed_data);
        }

        self::calculateAllGroups(null, $parsed_data);
    }

    /**
     * For Blocks Checkout (Store API) updates.
     */
    public static function calculateDistance($cart, $request): void
    {
        self::calculateAllGroups($request);
    }

    /**
     * Loop through all menu_ids in cart and calculate distance/fee for each.
     */
    private static function calculateAllGroups($request = null, $parsed_post_data = []): void
    {
        if (!WC()->cart) return;

        $menu_ids = [];
        foreach (WC()->cart->get_cart() as $item) {
            $menu_id = $item['menu_id'] ?? 'default';
            if (!in_array($menu_id, $menu_ids)) {
                $menu_ids[] = $menu_id;
            }
        }

        foreach ($menu_ids as $menu_id) {
            self::calculateCommon($menu_id, $request, $parsed_post_data);
        }
    }

    /**
     * Shared logic for distance calculation for a specific menu group.
     */
    private static function calculateCommon($menu_id = 'default', $request = null, $parsed_post_data = []): void
    {
        if (!WC()->session) return;

        $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
        $order_mode = WC()->session->get('order_mode' . $suffix);

        $has_party_order = self::hasPartyOrderInCart();

        if (!$order_mode && $has_party_order) {
            $order_mode = 'delivery';
            WC()->session->set('order_mode' . $suffix, 'delivery');
        }

        if ($order_mode === 'takeaway') {
            WC()->session->set('zippy_checkout_distance' . $suffix, 0);
            WC()->session->set('zippy_checkout_distance_meters' . $suffix, 0);
            WC()->session->set('shipping_fee' . $suffix, 0);
            return;
        }

        $address = '';
        $postcode = '';

        if ($request && isset($request['shipping_address'])) {
            $address = $request['shipping_address']['address_1'] ?? '';
            $postcode = $request['shipping_address']['postcode'] ?? '';
        } elseif (!empty($parsed_post_data)) {
            $address = !empty($parsed_post_data['shipping_address_1']) ? $parsed_post_data['shipping_address_1'] : ($parsed_post_data['billing_address_1'] ?? '');
            $postcode = !empty($parsed_post_data['shipping_postcode']) ? $parsed_post_data['shipping_postcode'] : ($parsed_post_data['billing_postcode'] ?? '');
        } elseif (WC()->customer) {
            $address = WC()->customer->get_shipping_address_1() ?: WC()->customer->get_billing_address_1();
            $postcode = WC()->customer->get_shipping_postcode() ?: WC()->customer->get_billing_postcode();
        }


        $distance_meters = 0;

        // Always recalculate distance if address is provided for Party Order
        if ($has_party_order && (!empty($address) || !empty($postcode))) {
            $origin = WC()->session->get('outlet_address' . $suffix);
            if (empty($origin)) {
                global $wpdb;
                $table = OUTLET_CONFIG_TABLE_NAME;
                $first_outlet = $wpdb->get_row("SELECT * FROM {$table} LIMIT 1");
                if ($first_outlet) {
                    $outlet_data = maybe_unserialize($first_outlet->outlet_address);
                    $origin = $outlet_data['address'] ?? '';
                }
            }

            if (!empty($origin)) {
                $distance_meters = self::getDistanceInMeters($origin, $address . ' ' . $postcode);
            }
        }

        if ($distance_meters > 0) {
            $distance_km = round($distance_meters / 1000, 2);
            WC()->session->set('zippy_checkout_distance' . $suffix, $distance_km);
            WC()->session->set('zippy_checkout_distance_meters' . $suffix, $distance_meters);
            WC()->session->set('total_distance' . $suffix, $distance_meters);
            WC()->session->set('billing_distance' . $suffix, $distance_km);

            // Calculate shipping fee based on distance
            if (class_exists('\Zippy_Booking\Src\Services\Zippy_Handle_Shipping')) {
                $shipping_config = \Zippy_Booking\Src\Services\Zippy_Handle_Shipping::query_shipping();
                if ($shipping_config) {
                    $rules = maybe_unserialize($shipping_config->minimum_order_to_delivery);
                    $fee = \Zippy_Booking\Src\Services\Zippy_Handle_Shipping::get_fee_from_config($rules, $distance_km);

                    WC()->session->set('shipping_fee' . $suffix, $fee);
                    update_option('debug_test', $fee);
                    error_log("AZ DEBUG: Distance: {$distance_km}km. Found Fee: $fee");
                }
            }
        }
    }

    private static function getDistanceInMeters($from, $to): float
    {
        $coord_from = self::getCoords($from);
        $coord_to = self::getCoords($to);
        if (!$coord_from || !$coord_to) return 0.0;
        $params = ['start' => $coord_from['lat'] . ',' . $coord_from['lng'], 'end' => $coord_to['lat'] . ',' . $coord_to['lng'], 'routeType' => 'drive'];
        $res = \Zippy_Booking\Src\Services\One_Map_Api::get('/api/public/routingsvc/route', $params);
        return isset($res['route_summary']['total_distance']) ? (float) $res['route_summary']['total_distance'] : 0.0;
    }

    private static function getCoords($address): ?array
    {
        $res = \Zippy_Booking\Src\Services\One_Map_Api::get('/api/common/elastic/search', ['searchVal' => $address, 'returnGeom' => 'Y', 'getAddrDetails' => 'Y']);
        if (!empty($res['results'][0])) {
            return ['lat' => $res['results'][0]['LATITUDE'], 'lng' => $res['results'][0]['LONGITUDE']];
        }
        return null;
    }

    public static function addDistanceToLabels($rates, $package): array
    {
        if (!WC()->session) return $rates;

        $menu_id = $package['menu_id'] ?? 'default';
        $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';

        $distance_meters = (float) WC()->session->get('total_distance' . $suffix);
        $shipping_fee = (float) WC()->session->get('shipping_fee' . $suffix);
        $order_mode = WC()->session->get('order_mode' . $suffix);
        $menu_name = self::get_menu_name($menu_id);

        if (empty($rates)) {
            $rate_id = 'custom_shipping_' . $menu_id;
            $base_label = ($order_mode === 'takeaway') ? __('Pick up at store', 'ai-zippy') : __('Shipping Fee', 'ai-zippy');
            $label = $menu_name ? sprintf('%s: %s', $base_label, $menu_name) : $base_label;

            $rates[$rate_id] = new \WC_Shipping_Rate(
                $rate_id,
                $label,
                $shipping_fee,
                [],
                'custom_shipping_method'
            );
        }

        foreach ($rates as $rate_id => $rate) {
            $label = strtolower($rate->get_label());

            if ($order_mode === 'takeaway') {
                if (strpos($label, 'take away') !== false || strpos($label, 'pickup') !== false || strpos($label, 'pick up') !== false) {
                    $outlet_name = WC()->session->get('outlet_name' . $suffix);
                    $base_label = $outlet_name ? sprintf(__('Pick up from %s', 'ai-zippy'), $outlet_name) : __('Pick up at store', 'ai-zippy');
                    $rate->set_label($menu_name ? sprintf('%s: %s', $base_label, $menu_name) : $base_label);
                    $rate->set_cost(0);
                } else {
                    unset($rates[$rate_id]);
                }
            } else {
                if ($menu_name && strpos($label, strtolower($menu_name)) === false) {
                    $rate->set_label($rate->get_label() . ': ' . $menu_name);
                }

                if (strpos($rate_id, 'flat_rate') !== false || strpos($rate_id, 'custom_shipping') !== false) {
                    $rate->set_cost($shipping_fee);
                }
            }
        }

        return $rates;
    }

    private static function get_menu_name($menu_id)
    {
        global $wpdb;
        if (empty($menu_id) || $menu_id === 'default') return '';
        $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}zippy_menus WHERE id = %d", $menu_id));
        return $name ? sanitize_text_field($name) : '';
    }

    /**
     * Force sync the sum of all group shipping fees to the cart shipping total.
     */
    public static function syncShippingTotalToCart($cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $total_shipping = 0;
        $menu_ids = [];
        foreach ($cart->get_cart() as $item) {
            $menu_id = $item['menu_id'] ?? 'default';
            if (!in_array($menu_id, (array)$menu_ids)) {
                $menu_ids[] = $menu_id;
                $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
                $fee = (float) WC()->session->get('shipping_fee' . $suffix);
                $total_shipping += $fee;
            }
        }

        if ($total_shipping > 0) {
            $cart->set_shipping_total($total_shipping);
            // Re-calculate total to include the new shipping total
            $cart->set_total($cart->get_subtotal() + $total_shipping + $cart->get_fee_total() + $cart->get_taxes_total());
        }
    }

    /**
     * Remove address fields from checkout since they are captured in session.
     */
    public static function removeAddressFields($fields): array
    {
        if (self::hasPartyOrderInCart()) {
            return $fields;
        }

        $address_fields = [
            'country',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode'
        ];

        foreach ($address_fields as $field) {
            unset($fields['billing']['billing_' . $field]);
            unset($fields['shipping']['shipping_' . $field]);
        }

        // Also remove the "Ship to a different address" checkbox
        unset($fields['shipping']['ship_to_different_address']);

        return $fields;
    }

    /**
     * Populate order address from session when creating the order.
     */
    public static function populateOrderAddressFromSession($order, $data): void
    {
        $delivery_address = WC()->session->get('delivery_address') ?: WC()->session->get('address_name');
        $postal = WC()->session->get('postal');

        if ($delivery_address) {
            $address = [
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'address_1'  => $delivery_address,
                'city'       => 'Singapore',
                'postcode'   => $postal,
                'country'    => 'SG',
            ];

            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping');
        }
    }

    /**
     * Ensure address is synced to customer object and a shipping method is selected.
     */
    public static function ensureAddressAndSelection(): void
    {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!WC()->customer || !WC()->session) return;

        $delivery_address = WC()->session->get('delivery_address') ?: WC()->session->get('address_name');
        $postal = WC()->session->get('postal');

        if ($delivery_address) {
            WC()->customer->set_billing_address_1($delivery_address);
            WC()->customer->set_shipping_address_1($delivery_address);
            WC()->customer->set_billing_postcode($postal);
            WC()->customer->set_shipping_postcode($postal);
            WC()->customer->set_billing_country('SG');
            WC()->customer->set_shipping_country('SG');
            WC()->customer->set_billing_city('Singapore');
            WC()->customer->set_shipping_city('Singapore');
        }

        // 2. Auto-select first shipping method for each package if none selected
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $packages = WC()->shipping()->get_packages();

        $updated = false;
        foreach ($packages as $i => $package) {
            if (!isset($chosen_methods[$i]) || empty($chosen_methods[$i])) {
                $rates = $package['rates'] ?? [];
                if (!empty($rates)) {
                    $keys = array_keys($rates);
                    $chosen_methods[$i] = $keys[0];
                    $updated = true;
                }
            }
        }

        if ($updated) {
            WC()->session->set('chosen_shipping_methods', $chosen_methods);
        }
    }
}
