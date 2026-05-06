<?php

namespace AiZippyChild\Inc;

defined('ABSPATH') || exit;

class SplitOrder
{
    private static $request_id = null;

    public static function init()
    {
        if (self::$request_id === null) {
            self::$request_id = uniqid();
        }

        add_filter('woocommerce_cart_shipping_packages', [self::class, 'split_shipping_packages']);
        add_filter('woocommerce_add_cart_item_data', [self::class, 'add_menu_id_to_cart_item'], 10, 3);
        add_filter('woocommerce_store_api_add_to_cart_data', [self::class, 'add_menu_id_to_store_api_cart'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [self::class, 'get_cart_item_from_session'], 10, 3);
        add_filter('woocommerce_get_item_data', [self::class, 'display_menu_id_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'save_menu_id_to_order_item'], 10, 4);
        add_action('woocommerce_checkout_order_processed', [self::class, 'split_order_by_menu'], 999, 3);
        add_filter('woocommerce_shipping_package_name', [self::class, 'rename_shipping_packages'], 10, 3);
        add_action('woocommerce_order_status_changed', [self::class, 'sync_status_to_suborders'], 10, 4);
    }

    /**
     * Custom logger that saves to a WordPress option.
     */
    public static function log($message)
    {
        $logs = get_option('zippy_split_debug_logs', []);
        if (!is_array($logs)) $logs = [];

        $logs[] = [
            'time' => current_time('mysql'),
            'msg'  => $message,
            'uri'  => $_SERVER['REQUEST_URI'] ?? '',
            'rid'  => self::$request_id
        ];

        // Keep last 150 entries
        if (count($logs) > 150) {
            $logs = array_slice($logs, -150);
        }

        update_option('zippy_split_debug_logs', $logs, false);
    }

    public static function add_menu_id_to_cart_item($cart_item_data, $product_id, $variation_id)
    {
        $menu_id = sanitize_text_field($_REQUEST['menu_id'] ?? '');
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

    public static function save_menu_id_to_order_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['menu_id'])) {
            $item->add_meta_data('_menu_id', $values['menu_id'], true);
        }
    }

    public static function split_shipping_packages($packages)
    {
        $new_packages = [];
        foreach (WC()->cart->get_cart() as $item_key => $item) {
            $menu_id = $item['menu_id'] ?? 'default';
            if (!isset($new_packages[$menu_id])) {
                $new_packages[$menu_id] = [
                    'contents'        => [],
                    'contents_cost'   => 0,
                    'applied_coupons' => WC()->cart->get_applied_coupons(),
                    'user'            => [
                        'ID' => get_current_user_id(),
                    ],
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
            $new_packages[$menu_id]['contents'][$item_key] = $item;
            $new_packages[$menu_id]['contents_cost'] += $item['line_total'];
        }
        return array_values($new_packages);
    }

    public static function rename_shipping_packages($package_name, $i, $package)
    {
        $menu_id = $package['menu_id'] ?? 'default';
        $menu_name = self::get_menu_name($menu_id);
        return $menu_name ?: $package_name;
    }

    private static function get_menu_name($menu_id)
    {
        global $wpdb;
        if (empty($menu_id) || $menu_id === 'default') return '';
        return $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}zippy_menus WHERE id = %d", $menu_id));
    }

    /**
     * CORE LOGIC: Split order by menu ID after checkout processing.
     */
    public static function split_order_by_menu($order_id, $posted_data, $order)
    {
        if ($order->get_meta('_zippy_split_done') || $order->get_created_via() === 'split_order') return;

        self::log("---------- START SPLIT ORDER #$order_id ----------");

        $items = $order->get_items();
        $shipping_items = $order->get_items('shipping');
        $groups = [];

        // 1. Group items by menu_id
        foreach ($items as $item) {
            $menu_id = (string)($item->get_meta('_menu_id') ?: 'default');
            if (!isset($groups[$menu_id])) {
                $groups[$menu_id] = ['items' => [], 'shipping' => null];
            }
            $groups[$menu_id]['items'][] = $item;
        }

        // 2. Assign shipping lines to groups
        foreach ($shipping_items as $sh_item) {
            $label = $sh_item->get_name();
            foreach ($groups as $menu_id => &$data) {
                $menu_name = self::get_menu_name($menu_id);
                if ($menu_name && strpos($label, $menu_name) !== false) {
                    $data['shipping'] = $sh_item;
                    break;
                }
            }
        }

        if (count($groups) <= 1) {
            self::log("Only one menu group found. No split needed.");
            $menu_id = array_key_first($groups);
            self::save_session_to_order($order, $menu_id);
            $order->update_meta_data('_zippy_split_done', 'yes');
            $order->save();
            return;
        }

        $menu_ids = array_keys($groups);
        $parent_menu_id = array_shift($menu_ids);

        self::log("Splitting Parent #$order_id. Keeping Menu: $parent_menu_id. Moving: " . implode(', ', $menu_ids));

        // 3. Create Sub-orders for remaining groups
        foreach ($menu_ids as $menu_id) {
            $data = $groups[$menu_id];
            
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

            // Move items
            foreach ($data['items'] as $item) {
                $new_item = new \WC_Order_Item_Product();
                $new_item->set_props([
                    'product_id'   => $item->get_product_id(),
                    'variation_id' => $item->get_variation_id(),
                    'quantity'     => $item->get_quantity(),
                    'subtotal'     => $item->get_subtotal(),
                    'total'        => $item->get_total(),
                    'name'         => $item->get_name(),
                ]);
                foreach ($item->get_meta_data() as $meta) {
                    $new_item->add_meta_data($meta->key, $meta->value, true);
                }
                $sub_order->add_item($new_item);
                $order->remove_item($item->get_id());
            }

            // Move shipping
            if ($data['shipping']) {
                $sh = $data['shipping'];
                $new_sh = new \WC_Order_Item_Shipping();
                $new_sh->set_props([
                    'method_title' => $sh->get_method_title(),
                    'method_id'    => $sh->get_method_id(),
                    'instance_id'  => $sh->get_instance_id(),
                    'total'        => $sh->get_total(),
                ]);
                $sub_order->add_item($new_sh);
                $order->remove_item($sh->get_id());
            }

            self::save_session_to_order($sub_order, $menu_id);
            $sub_order->set_parent_id($order_id);
            $sub_order->update_meta_data('_zippy_split_done', 'yes');
            $sub_order->calculate_totals();
            $sub_order->save();
            
            self::log("Created Sub-Order #" . $sub_order->get_id() . " for Menu: $menu_id");
        }

        // 4. Update Parent Order
        self::save_session_to_order($order, $parent_menu_id);
        $order->update_meta_data('_zippy_split_done', 'yes');
        $order->calculate_totals();
        $order->save();

        self::log("Parent Order #$order_id updated.");
    }

    private static function save_session_to_order($order, $menu_id)
    {
        $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
        $keys = ['order_mode', 'date', 'time', 'outlet_name', 'outlet_address', 'delivery_address', 'postal', 'total_distance'];

        foreach ($keys as $key) {
            $val = \WC()->session->get($key . $suffix);
            if ($val) {
                $order->update_meta_data('_billing_' . $key, $val);
            }
        }
    }

    /**
     * Sync status from parent order to all its sub-orders.
     */
    public static function sync_status_to_suborders($order_id, $old_status, $new_status, $order)
    {
        if (!$order_id || !$order) return;

        // Only proceed if this is a parent order (parent_id is 0)
        // and it wasn't created via split_order itself.
        if ($order->get_parent_id() !== 0 || $order->get_created_via() === 'split_order') {
            return;
        }
        
        $sub_orders = wc_get_orders([
            'parent' => $order_id,
            'status' => 'any', // Crucial: find orders even if they are pending-payment
            'return' => 'ids',
            'limit'  => -1,
        ]);

        if (empty($sub_orders)) {
            return;
        }

        self::log("Syncing status '$new_status' from Parent #$order_id to " . count($sub_orders) . " sub-orders.");

        foreach ($sub_orders as $sub_id) {
            $sub_order = wc_get_order($sub_id);
            if ($sub_order && $sub_order->get_status() !== $new_status) {
                $sub_order->update_status($new_status, sprintf(__('Synced status from parent order #%d.', 'ai-zippy'), $order_id));
            }
        }
    }
}
