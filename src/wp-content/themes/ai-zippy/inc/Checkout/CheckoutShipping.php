<?php

namespace AiZippy\Checkout;

use Zippy_Booking\Src\Services\Zippy_Handle_Shipping;

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
        add_filter('woocommerce_update_order_review_fragments', [self::class, 'updateSidebarFragments'], 10);

        // Minimum Delivery Order Validation
        add_action('woocommerce_checkout_process', [self::class, 'validateMinimumDeliveryOrder']);
        add_action('woocommerce_check_cart_items', [self::class, 'validateMinimumDeliveryOrder']);
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

    /**
     * Check if the current cart has ONLY party order products.
     */
    public static function isOnlyPartyOrderInCart(): bool
    {
        if (!WC()->cart || WC()->cart->is_empty()) return false;

        foreach (WC()->cart->get_cart() as $item) {
            if (!self::isPartyOrderProduct($item['product_id'])) {
                return false;
            }
        }
        return true;
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
    public static function calculateDistanceClassic($post_data = null): void
    {
        if (!WC()->session) return;

        // If post_data is not passed directly, try to get it from $_POST
        if (empty($post_data) && isset($_POST['post_data'])) {
            $post_data = $_POST['post_data'];
        }

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
            self::calculateGroupShipping($menu_id, $request, $parsed_post_data);
        }
    }

    /**
     * Coordinator method to compute and persist shipping parameters for a specific menu group.
     */
    private static function calculateGroupShipping($menu_id = 'default', $request = null, $parsed_post_data = []): void
    {
        if (!WC()->session) return;

        $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';

        // Case 1: Delivery Method is Takeaway -> Free Shipping
        if (WC()->session->get('order_mode' . $suffix) === 'takeaway') {
            self::saveShippingSessionData($suffix, 0.0, 0.0, 0.0);
            return;
        }

        // Case 2: Resolve customer's physical destination details
        list($address, $postcode) = self::resolveCustomerAddressAndPostcode($request, $parsed_post_data);

        // Case 3: Measure driving distance between outlet origin and destination
        $distance_meters = self::calculateDistanceMeters($address, $postcode, $suffix);
        $distance_km = round($distance_meters / 1000, 2);

        // Case 4: Calculate fee based on either postal overrides or distance tiers
        $fee = self::calculateBaseShippingFee($distance_km, $distance_meters, $postcode);

        // Case 5: Evaluate eligibility for the Free Shipping threshold
        $fee = self::applyFreeShippingThreshold($fee, $distance_km);

        // Case 6: Save the computed parameters to checkout session if resolved
        if ($fee !== null) {
            self::saveShippingSessionData($suffix, $distance_km, $distance_meters, $fee);
        }
    }

    /**
     * Resolve the customer's destination address and postcode from current checkout payloads or user session.
     */
    private static function resolveCustomerAddressAndPostcode($request, $parsed_post_data): array
    {
        $address = '';
        $postcode = '';

        if ($request && isset($request['shipping_address'])) {
            $address  = $request['shipping_address']['address_1'] ?? '';
            $postcode = $request['shipping_address']['postcode'] ?? '';
        } elseif (!empty($parsed_post_data)) {
            $address  = !empty($parsed_post_data['shipping_address_1']) ? $parsed_post_data['shipping_address_1'] : ($parsed_post_data['billing_address_1'] ?? '');
            $postcode = !empty($parsed_post_data['shipping_postcode']) ? $parsed_post_data['shipping_postcode'] : ($parsed_post_data['billing_postcode'] ?? '');
        } elseif (WC()->customer) {
            $address  = WC()->customer->get_shipping_address_1() ?: WC()->customer->get_billing_address_1();
            $postcode = WC()->customer->get_shipping_postcode() ?: WC()->customer->get_billing_postcode();
        }

        return [$address, $postcode];
    }

    /**
     * Calculate driving distance in meters from the matching outlet (origin) to the customer address (destination).
     */
    private static function calculateDistanceMeters(string $address, string $postcode, string $suffix): float
    {
        $has_party_order = self::hasPartyOrderInCart();
        $is_pure_party_order = self::isOnlyPartyOrderInCart();
        $order_mode = WC()->session->get('order_mode' . $suffix);

        // Fallback for mixed orders or regular products
        if (!$order_mode && $has_party_order && !$is_pure_party_order) {
            WC()->session->set('order_mode' . $suffix, 'delivery');
        }

        if (!$has_party_order || (empty($address) && empty($postcode))) {
            return 0.0;
        }

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

        if (empty($origin)) {
            return 0.0;
        }

        return self::getDistanceInMeters($origin, $address . ' ' . $postcode);
    }

    /**
     * Calculate base shipping fee by looking up postal code overrides first, and then distance-based pricing tiers.
     */
    private static function calculateBaseShippingFee(float $distance_km, float $distance_meters, string $postcode): ?float
    {
        // 1. Check for Postal Code overrides (tab "Delivery Extra Fee")
        if (class_exists(Zippy_Handle_Shipping::class)) {
            $override_fee = Zippy_Handle_Shipping::get_postal_code_override_fee($postcode);
            if ($override_fee !== null) {
                return (float)$override_fee;
            }
        }

        // 2. Check for Distance-based tiers (tab "Delivery Fee")
        if ($distance_meters > 0 && class_exists(Zippy_Handle_Shipping::class)) {
            $shipping_config = Zippy_Handle_Shipping::query_shipping();
            if ($shipping_config) {
                $rules = maybe_unserialize($shipping_config->minimum_order_to_delivery);
                return (float)Zippy_Handle_Shipping::get_fee_from_config($rules, $distance_km);
            }
        }

        return null;
    }

    /**
     * Apply free shipping waiver if order subtotal is greater than or equal to the minimum threshold for their distance range.
     */
    private static function applyFreeShippingThreshold(?float $fee, float $distance_km): ?float
    {
        if ($fee === null || !class_exists(Zippy_Handle_Shipping::class)) {
            return $fee;
        }

        $shipping_config = Zippy_Handle_Shipping::query_shipping();
        if ($shipping_config) {
            $freeship_rules = maybe_unserialize($shipping_config->minimum_order_to_freeship);
            $freeship_threshold = Zippy_Handle_Shipping::get_fee_from_config($freeship_rules, $distance_km);

            $cart_subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0.0;
            if ($freeship_threshold > 0 && $cart_subtotal >= $freeship_threshold) {
                return 0.0; // Meets or exceeds threshold for Free Shipping
            }
        }

        return $fee;
    }

    /**
     * Write calculated shipping rates and distances into the WooCommerce checkout session variables.
     */
    private static function saveShippingSessionData(string $suffix, float $distance_km, float $distance_meters, float $fee): void
    {
        WC()->session->set('zippy_checkout_distance' . $suffix, $distance_km);
        WC()->session->set('zippy_checkout_distance_meters' . $suffix, $distance_meters);
        WC()->session->set('total_distance' . $suffix, $distance_meters);
        WC()->session->set('billing_distance' . $suffix, $distance_km);
        WC()->session->set('shipping_fee' . $suffix, $fee);
        update_option('debug_test', $fee);
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

        // Force recalculation during AJAX update to ensure we use the latest address from the form
        if (wp_doing_ajax() && isset($_POST['post_data'])) {
            self::calculateDistanceClassic($_POST['post_data']);
        }

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
        if (!WC()->session) {
            return $fields;
        }

        // Check if ANY menu group has an active order mode session
        $has_order_mode = false;

        // Check default session
        if (WC()->session->get('order_mode')) {
            $has_order_mode = true;
        } else {
            // Check all menu IDs in cart
            foreach (WC()->cart->get_cart() as $item) {
                $menu_id = $item['menu_id'] ?? 'default';
                $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
                if (WC()->session->get('order_mode' . $suffix)) {
                    $has_order_mode = true;
                    break;
                }
            }
        }

        // If no pre-order session is active, we show fields (especially for Party Orders)
        if (!$has_order_mode) {
            return $fields;
        }

        // We have a pre-order session, so hide redundant address fields
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

    /**
     * Validate that each menu group designated for delivery meets the minimum delivery purchase limit.
     */
    public static function validateMinimumDeliveryOrder(): void
    {
        if (!WC()->session || !WC()->cart) return;

        $min_delivery_order = (float) get_option('minimum_order', 0);
        if ($min_delivery_order <= 0) return;

        // 1. Group cart line subtotals by menu_id
        $group_subtotals = [];
        foreach (WC()->cart->get_cart() as $cart_item) {
            $menu_id = $cart_item['menu_id'] ?? 'default';
            if (!isset($group_subtotals[$menu_id])) {
                $group_subtotals[$menu_id] = 0.0;
            }
            $group_subtotals[$menu_id] += (float) $cart_item['line_subtotal'];
        }

        // 2. Validate each group independently against the minimum limit
        foreach ($group_subtotals as $menu_id => $subtotal) {
            $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
            $order_mode = WC()->session->get('order_mode' . $suffix);

            if ($order_mode === 'delivery') {
                if ($subtotal < $min_delivery_order) {
                    $menu_name = self::get_menu_name($menu_id) ?: __('Default Menu', 'ai-zippy');
                    $shortage = $min_delivery_order - $subtotal;

                    $message = sprintf(
                        __('The minimum order amount for delivery of "%s" is %s. You need to add %s more of these items or switch this menu to Takeaway to proceed.', 'ai-zippy'),
                        $menu_name,
                        wc_price($min_delivery_order),
                        wc_price($shortage)
                    );
                    wc_add_notice($message, 'error');
                }
            }
        }
    }

    public static function updateSidebarFragments($fragments): array
    {
        ob_start();
?>
        <div class="omi-grouped-container">
            <?php
            $grouped_items = [];
            foreach (WC()->cart->get_cart() as $key => $item) {
                $menu_id = $item['menu_id'] ?? 'default';
                if (!isset($grouped_items[$menu_id])) {
                    $grouped_items[$menu_id] = [];
                }
                $grouped_items[$menu_id][$key] = $item;
            }

            foreach ($grouped_items as $menu_id => $items) :
                $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
                $g_order_mode = WC()->session->get('order_mode' . $suffix);
                $g_outlet_name = WC()->session->get('outlet_name' . $suffix);
                $g_date = WC()->session->get('date' . $suffix);
                $g_time = WC()->session->get('time' . $suffix);
                $g_shipping_fee = (float) WC()->session->get('shipping_fee' . $suffix);

                $g_formatted_date = ! empty($g_date) ? date('D, M d, Y', strtotime($g_date)) : '-';
                $g_formatted_time = '-';
                if (! empty($g_time)) {
                    $g_time_data = is_string($g_time) ? json_decode($g_time, true) : $g_time;
                    $g_formatted_time = (is_array($g_time_data) && isset($g_time_data['from'])) ? "{$g_time_data['from']} - {$g_time_data['to']}" : (string)$g_time;
                }
            ?>
                <div class="omi-group-card">
                    <div class="omi-group-card__header">
                        <div class="omi-group-card__info">
                            <div>
                                <span class="omi-label">MODE:</span>
                                <span class="omi-value"><?php echo esc_html($g_order_mode === 'takeaway' ? 'Takeaway' : 'Delivery'); ?></span>
                            </div>
                            <div>
                                <span class="omi-label">OUTLET:</span>
                                <span class="omi-value"><?php echo esc_html($g_outlet_name ?: '-'); ?></span>
                            </div>
                            <div>
                                <span class="omi-label">TIME:</span>
                                <span class="omi-value"><?php echo esc_html($g_formatted_date); ?> (<?php echo esc_html($g_formatted_time); ?>)</span>
                            </div>
                        </div>
                        <button type="button" class="omi-group-card__reset az-checkout__group-reset" data-menu-id="<?php echo esc_attr($menu_id); ?>">RESET</button>
                    </div>
                    <div class="omi-group-card__items">
                        <?php foreach ($items as $key => $item) :
                            $_product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);
                            if ($_product && $_product->exists() && $item['quantity'] > 0) :
                        ?>
                                <div class="az-checkout__item omi-item" data-cart-key="<?php echo esc_attr($key); ?>">
                                    <div class="az-checkout__item-img omi-item__image"><?php echo $_product->get_image(); ?></div>
                                    <div class="az-checkout__item-detail omi-item__content">
                                        <div class="az-checkout__item-top">
                                            <span class="az-checkout__item-name omi-item__name"><?php echo $_product->get_name(); ?></span>
                                        </div>
                                        <div class="az-checkout__item-bottom omi-item__meta">
                                            <span class="az-checkout__item-meta">Qty: <?php echo $item['quantity']; ?></span>
                                            <span class="az-checkout__item-total"><?php echo WC()->cart->get_product_subtotal($_product, $item['quantity']); ?></span>
                                        </div>
                                    </div>
                                </div>
                        <?php endif;
                        endforeach; ?>

                        <!-- GROUP SHIPPING FEE -->
                        <div class="omi-group-shipping">
                            <span class="omi-label">SHIPPING:</span>
                            <span class="omi-value">
                                <?php if ($g_order_mode === 'takeaway') : ?>
                                    <strong>Free</strong>
                                <?php else : ?>
                                    <strong><?php echo wc_price($g_shipping_fee); ?></strong>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="az-checkout__totals">
            <div class="az-checkout__totals-row">
                <span>Subtotal</span>
                <span><?php wc_cart_totals_subtotal_html(); ?></span>
            </div>

            <!-- TOTAL SHIPPING -->
            <div class="az-checkout__totals-row">
                <span>Shipping Total</span>
                <span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
            </div>

            <?php foreach (WC()->cart->get_fees() as $fee) : ?>
                <div class="az-checkout__totals-row">
                    <span><?php echo esc_html($fee->name); ?></span>
                    <span><?php wc_cart_totals_fee_html($fee); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="az-checkout__totals-row az-checkout__totals-row--total">
                <span>Total</span>
                <span><?php wc_cart_totals_order_total_html(); ?></span>
            </div>
        </div>

        <div id="order_review" class="woocommerce-checkout-review-order">
            <?php do_action('woocommerce_checkout_order_review'); ?>
        </div>
<?php
        $html = ob_get_clean();

        $fragments['#az-checkout-sidebar-fragments'] = '<div class="az-checkout__card-body" id="az-checkout-sidebar-fragments">' . $html . '</div>';

        return $fragments;
    }
}
