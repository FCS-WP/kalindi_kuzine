<?php

namespace AiZippyChild\Inc;

use Zippy_Booking\Utils\Zippy_Logger;

defined('ABSPATH') || exit;

class SplitOrder
{
    private static $request_id = null;

    public static function init()
    {
        if (self::$request_id === null) {
            self::$request_id = uniqid();
        }
        self::log("Init called for SplitOrder. Request ID: " . self::$request_id);

        add_filter('woocommerce_cart_shipping_packages', [self::class, 'split_shipping_packages']);
        add_filter('woocommerce_add_cart_item_data', [self::class, 'add_menu_id_to_cart_item'], 10, 3);
        add_filter('woocommerce_store_api_add_to_cart_data', [self::class, 'add_menu_id_to_store_api_cart'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [self::class, 'get_cart_item_from_session'], 10, 3);
        add_filter('woocommerce_get_item_data', [self::class, 'display_menu_id_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'save_menu_id_to_order_item'], 10, 4);
        add_action('woocommerce_checkout_order_created', [self::class, 'detect_and_trash_ghost'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [self::class, 'split_order_by_menu'], 999, 3);
        add_filter('woocommerce_shipping_package_name', [self::class, 'rename_shipping_packages'], 10, 3);

        // Sync status from parent to children
        // add_action('woocommerce_order_status_changed', [self::class, 'sync_sub_orders_status'], 10, 4);
    }

    private static function log($message)
    {
        $rid = self::$request_id ?: 'no-rid';
        $uri = $_SERVER['REQUEST_URI'] ?? 'no-uri';
        // Zippy_Logger::log("[RID: $rid] [URI: $uri] " . $message, 'SplitOrder');
    }

    public static function sync_sub_orders_status($order_id, $old_status, $new_status, $order)
    {
        remove_action('woocommerce_order_status_changed', [self::class, 'sync_sub_orders_status'], 10);

        $sub_orders = wc_get_orders([
            'parent_id' => $order_id,
            'limit'     => -1,
        ]);

        if (!empty($sub_orders)) {
            self::log("Syncing status '$new_status' from Parent #$order_id to " . count($sub_orders) . " sub-orders.");
            foreach ($sub_orders as $sub_order) {
                $sub_order->update_status($new_status, sprintf(__('Status synced from parent order #%d.', 'ai-zippy'), $order_id));
            }
        }

        add_action('woocommerce_order_status_changed', [self::class, 'sync_sub_orders_status'], 10, 4);
    }

    public static function add_menu_id_to_cart_item($cart_item_data, $product_id, $variation_id)
    {
        $menu_id = $cart_item_data['menu_id'] ?? '';
        if (empty($menu_id)) $menu_id = sanitize_text_field($_REQUEST['menu_id'] ?? '');

        if ($menu_id) {
            $cart_item_data['menu_id'] = $menu_id;
            $cart_item_data['unique_key'] = md5($menu_id . '_' . $product_id . '_' . microtime());
        }
        return $cart_item_data;
    }

    public static function add_menu_id_to_store_api_cart($cart_item_data, $request)
    {
        $menu_id = sanitize_text_field($request['menu_id'] ?? '');
        if ($menu_id) {
            $cart_item_data['menu_id'] = $menu_id;
            $cart_item_data['unique_key'] = md5($menu_id . '_' . ($cart_item_data['product_id'] ?? '') . '_' . microtime());
        }
        return $cart_item_data;
    }

    public static function get_cart_item_from_session($cart_item, $values, $cart_item_key)
    {
        if (isset($values['menu_id'])) $cart_item['menu_id'] = $values['menu_id'];
        return $cart_item;
    }

    public static function display_menu_id_in_cart($item_data, $cart_item)
    {
        if (isset($cart_item['menu_id'])) {
            $menu_name = self::get_menu_name($cart_item['menu_id']);
            if ($menu_name) $item_data[] = ['key' => __('Menu / Day', 'ai-zippy'), 'value' => $menu_name];
        }
        return $item_data;
    }

    private static function get_menu_name($menu_id)
    {
        global $wpdb;
        if (empty($menu_id) || $menu_id === 'default') return '';
        return $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}zippy_menus WHERE id = %d", $menu_id));
    }

    public static function split_shipping_packages($packages)
    {
        if (empty(WC()->cart->get_cart())) return $packages;

        $split_packages = [];
        foreach (WC()->cart->get_cart() as $item_key => $item) {
            $menu_id = $item['menu_id'] ?? 'default';
            if (!isset($split_packages[$menu_id])) {
                $split_packages[$menu_id] = [
                    'contents'        => [],
                    'contents_cost'   => 0,
                    'applied_coupons' => WC()->cart->get_applied_coupons(),
                    'user'            => ['ID' => get_current_user_id()],
                    'destination'     => [
                        'country'   => WC()->customer->get_shipping_country(),
                        'state'     => WC()->customer->get_shipping_state(),
                        'postcode'  => WC()->customer->get_shipping_postcode(),
                        'city'      => WC()->customer->get_shipping_city(),
                        'address'   => WC()->customer->get_shipping_address(),
                        'address_2' => WC()->customer->get_shipping_address_2(),
                    ],
                    'menu_id'         => $menu_id,
                ];
            }
            $split_packages[$menu_id]['contents'][$item_key] = $item;
            $split_packages[$menu_id]['contents_cost'] += $item['line_total'];
        }
        return array_values($split_packages);
    }

    public static function rename_shipping_packages($name, $i, $package)
    {
        $menu_id = $package['menu_id'] ?? '';
        if ($menu_id) {
            $menu_name = self::get_menu_name($menu_id);
            $suffix = ($menu_id !== 'default') ? '_' . $menu_id : '';
            $order_mode = WC()->session ? WC()->session->get('order_mode' . $suffix) : '';
            $prefix = ($order_mode === 'takeaway') ? __('Takeaway', 'ai-zippy') : __('Delivery', 'ai-zippy');
            if ($menu_name) return sprintf('%s: %s', $prefix, $menu_name);
        }
        return $name;
    }

    public static function save_menu_id_to_order_item($item, $cart_item_key, $values, $order)
    {
        $order_id = $order->get_id();
        if (isset($values['menu_id'])) {
            $item->add_meta_data('_menu_id', $values['menu_id'], true);
            self::log("SUCCESS: Saved menu_id " . $values['menu_id'] . " to order item " . $item->get_name() . " (Order #$order_id)");
        } else {
            self::log("WARNING: menu_id missing in cart values for item " . $item->get_name() . " (Order #$order_id)");
            // Fallback: try to find it in the cart directly
            foreach (WC()->cart->get_cart() as $ckey => $citem) {
                if ($ckey === $cart_item_key && isset($citem['menu_id'])) {
                    $item->add_meta_data('_menu_id', $citem['menu_id'], true);
                    self::log("RECOVERY: Found menu_id " . $citem['menu_id'] . " in cart for item " . $item->get_name() . " (Order #$order_id)");
                    break;
                }
            }
        }
    }



    public static function detect_and_trash_ghost($order)
    {
        $order_id = $order->get_id();
        $billing_email = $order->get_billing_email();
        $customer_id = $order->get_customer_id();

        if (!$billing_email && !$customer_id) return;

        $lock_key = 'zippy_lock_' . md5($customer_id ?: $billing_email);
        $last_id = get_transient($lock_key);

        self::log("Hook order_created fired for #$order_id. Lock key: $lock_key, Last ID: $last_id");

        if ($last_id && $last_id != $order_id) {
            wp_cache_delete($last_id, 'orders');
            $last_order = wc_get_order($last_id);
            if ($last_order && !$last_order->get_meta('_zippy_split_done') && $last_order->get_status() !== 'trash') {
                self::log("GHOST DETECTED via Transient: Trashing #$last_id because new order #$order_id is being created.");
                $last_order->update_status('trash', 'Duplicate ghost order detected via transient lock.');
            }
        }

        set_transient($lock_key, $order_id, 60);
    }

    public static function split_order_by_menu($order_id, $posted_data, $order)
    {
        if ($order->get_meta('_zippy_split_done') || $order->get_created_via() === 'split_order') return;

        self::log("---------- START SPLIT PROCESS ORDER #$order_id ----------");

        // Double check for ghost in transient right before splitting
        $billing_email = $order->get_billing_email();
        $customer_id = $order->get_customer_id();
        if ($billing_email || $customer_id) {
            $lock_key = 'zippy_lock_' . md5($customer_id ?: $billing_email);
            $last_id = get_transient($lock_key);
            if ($last_id && $last_id != $order_id) {
                wp_cache_delete($last_id, 'orders');
                $last_order = wc_get_order($last_id);
                if ($last_order && !$last_order->get_meta('_zippy_split_done') && $last_order->get_status() !== 'trash') {
                    self::log("CLEANUP IN SPLIT (Transient): Trashing ghost #$last_id before processing #$order_id");
                    $last_order->update_status('trash', 'Ghost order cleaned up before splitting current order.');
                }
            }
        }

        // SELF-CHECK: If another request (ghost cleanup) trashed this order, ABORT processing.
        wp_cache_delete($order_id, 'orders');
        $fresh_order = wc_get_order($order_id);
        if ($fresh_order && $fresh_order->get_status() === 'trash') {
            self::log("ABORT: Order #$order_id was trashed by another request. Stopping split process to prevent resurrection.");
            return;
        }

        // Refresh order to ensure all meta is loaded
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        $shipping_items = $order->get_items('shipping');
        $menus = [];

        self::log("Step 1: Grouping " . count($items) . " items and " . count($shipping_items) . " shipping lines.");

        foreach ($items as $item_id => $item) {
            $menu_id = (string)($item->get_meta('_menu_id') ?: 'default');
            if (!isset($menus[$menu_id])) {
                $menus[$menu_id] = ['items' => [], 'shipping' => null];
            }
            $menus[$menu_id]['items'][] = $item;
            self::log("- Item #$item_id (" . $item->get_name() . ") assigned to Menu ID: $menu_id");
        }

        foreach ($shipping_items as $sh_item) {
            $label = $sh_item->get_name();
            $found_match = false;
            foreach ($menus as $menu_id => &$data) {
                $menu_name = self::get_menu_name($menu_id);
                if ($menu_name && strpos($label, $menu_name) !== false) {
                    $data['shipping'] = $sh_item;
                    $found_match = true;
                    self::log("- Shipping '$label' matched to Menu ID: $menu_id");
                    break;
                }
            }
            if (!$found_match) self::log("- Shipping '$label' NOT matched to any menu.");
        }
        unset($data);

        // Filter out groups with no product items
        $menus = array_filter($menus, function ($data) {
            return !empty($data['items']);
        });

        $count = count($menus);
        self::log("Step 2: Found $count valid groups: " . implode(', ', array_keys($menus)));

        if ($count <= 1) {
            self::log("Step 3: Single group found. Marking as done and exiting.");
            self::save_session_to_order($order, array_key_first($menus));
            $order->update_meta_data('_zippy_split_done', 'yes');
            $order->save();
            self::clear_all_session_data($menus);
            return;
        }

        $menu_ids = array_keys($menus);

        // Decide which group is the parent
        $first_menu_id = '';
        foreach ($menu_ids as $m_id) {
            if ($m_id !== 'default') {
                $first_menu_id = $m_id;
                break;
            }
        }
        if (!$first_menu_id) $first_menu_id = array_shift($menu_ids);
        else {
            $menu_ids = array_diff($menu_ids, [$first_menu_id]);
        }

        self::log("Step 4: Transforming Parent #$order_id into group $first_menu_id.");

        // Transform Parent Order
        foreach ($order->get_items() as $item_id => $item) {
            $item_menu = (string)($item->get_meta('_menu_id') ?: 'default');
            if ($item_menu != $first_menu_id) {
                self::log("- Parent: Removing Item #$item_id (" . $item->get_name() . ") [Menu: $item_menu]");
                $order->remove_item($item_id);
                wc_delete_order_item($item_id);
            } else {
                self::log("- Parent: KEEPING Item #$item_id (" . $item->get_name() . ") [Menu: $item_menu]");
            }
        }

        $first_group_shipping = $menus[$first_menu_id]['shipping'];
        foreach ($order->get_items('shipping') as $sh_id => $sh_item) {
            if (!$first_group_shipping || $sh_id != $first_group_shipping->get_id()) {
                self::log("- Parent: Removing Shipping #$sh_id (" . $sh_item->get_name() . ")");
                $order->remove_item($sh_id);
                wc_delete_order_item($sh_id);
            } else {
                self::log("- Parent: KEEPING Shipping #$sh_id (" . $sh_item->get_name() . ")");
            }
        }

        self::save_session_to_order($order, $first_menu_id);
        $order->update_meta_data('_zippy_split_done', 'yes');
        $order->calculate_totals();
        $order->save();
        self::log("- Parent #$order_id transformation saved. New Total: " . $order->get_total());

        // Create Sub-orders
        foreach ($menu_ids as $menu_id) {
            $data = $menus[$menu_id];
            self::log("Step 5: Creating NEW Sub-Order for group $menu_id.");

            $sub_order = wc_create_order([
                'status'        => $order->get_status(),
                'customer_id'   => $order->get_customer_id(),
                'customer_note' => $order->get_customer_note(),
                'created_via'   => 'split_order',
                'parent_id'     => $order_id,
            ]);

            $sub_order->set_address($order->get_address('billing'), 'billing');
            $sub_order->set_address($order->get_address('shipping'), 'shipping');
            $sub_order->set_payment_method($order->get_payment_method());
            $sub_order->set_payment_method_title($order->get_payment_method_title());

            $sub_order->update_meta_data('_zippy_parent_order_id', $order_id);
            $sub_order->update_meta_data('_zippy_split_done', 'yes');

            self::log("- Sub-Order #" . $sub_order->get_id() . ": Adding " . count($data['items']) . " items.");

            foreach ($data['items'] as $item) {
                $pid = $item->get_product_id();
                $pname = $item->get_name();
                self::log("  * Adding Product ID $pid ($pname). Meta _menu_id: " . $item->get_meta('_menu_id'));

                $new_item = new \WC_Order_Item_Product();
                $new_item->set_product(wc_get_product($pid));
                $new_item->set_quantity($item->get_quantity());
                $new_item->set_subtotal($item->get_subtotal());
                $new_item->set_total($item->get_total());
                if ($item->get_variation_id()) $new_item->set_variation_id($item->get_variation_id());
                $new_item->add_meta_data('_menu_id', $menu_id, true);

                $sub_order->add_item($new_item);
            }

            if ($data['shipping']) {
                self::log("- Sub-Order #" . $sub_order->get_id() . ": Adding shipping '" . $data['shipping']->get_name() . "'.");
                $new_sh = new \WC_Order_Item_Shipping();
                $new_sh->set_props([
                    'method_title' => $data['shipping']->get_method_title(),
                    'method_id'    => $data['shipping']->get_method_id(),
                    'instance_id'  => $data['shipping']->get_instance_id(),
                    'total'        => $data['shipping']->get_total(),
                ]);
                $sub_order->add_item($new_sh);
            }

            self::save_session_to_order($sub_order, $menu_id);
            $sub_order->calculate_totals();
            $sub_order->save();
            self::log("- Sub-Order #" . $sub_order->get_id() . " saved. Total: " . $sub_order->get_total());
        }

        self::log("---------- END SPLIT PROCESS ORDER #$order_id ----------");
        self::clear_all_session_data($menus);
    }

    private static function save_session_to_order($order, $menu_id)
    {
        if (!WC()->session) return;
        $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
        $order_mode = WC()->session->get('order_mode' . $suffix);
        $date       = WC()->session->get('date' . $suffix);
        $time       = WC()->session->get('time' . $suffix);
        $outlet     = WC()->session->get('outlet_name' . $suffix);

        if ($order_mode) {
            $order->update_meta_data('_zippy_order_mode', $order_mode);
            $order->update_meta_data('_billing_method_shipping', $order_mode);
        }
        if ($outlet) $order->update_meta_data('_zippy_outlet_name', $outlet);

        if ($date) {
            $order->update_meta_data('_zippy_date', $date);
            $order->update_meta_data('_billing_date', $date);
        }
        if ($time) {
            $time_val = is_array($time) ? json_encode($time) : $time;
            $order->update_meta_data('_zippy_time', $time_val);
            $order->update_meta_data('_billing_time', $time_val);
        }
    }

    private static function clear_all_session_data($menus)
    {
        if (!WC()->session) return;

        $keys = [
            'order_mode',
            'date',
            'time',
            'outlet_id',
            'outlet_name',
            'outlet_address',
            'delivery_address',
            'postal',
            'total_distance',
            'shipping_fee',
            'status_popup'
        ];

        foreach (array_keys($menus) as $menu_id) {
            $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
            foreach ($keys as $key) {
                WC()->session->set($key . $suffix, null);
            }
        }

        foreach ($keys as $key) {
            WC()->session->set($key, null);
        }

        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }
}
