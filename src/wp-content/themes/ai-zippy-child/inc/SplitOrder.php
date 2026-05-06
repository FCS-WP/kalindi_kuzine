<?php

namespace AiZippyChild\Inc;

defined('ABSPATH') || exit;

class SplitOrder
{
    public static function init()
    {
        add_filter('woocommerce_cart_shipping_packages', [self::class, 'split_shipping_packages']);
        add_filter('woocommerce_add_cart_item_data', [self::class, 'add_menu_id_to_cart_item'], 10, 3);
        add_filter('woocommerce_store_api_add_to_cart_data', [self::class, 'add_menu_id_to_store_api_cart'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [self::class, 'get_cart_item_from_session'], 10, 3);
        add_filter('woocommerce_get_item_data', [self::class, 'display_menu_id_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [self::class, 'save_menu_id_to_order_item'], 10, 4);
        add_action('woocommerce_checkout_order_processed', [self::class, 'split_order_by_menu'], 20, 3);
        add_filter('woocommerce_shipping_package_name', [self::class, 'rename_shipping_packages'], 10, 3);
        
        // Sync status from parent to children
        add_action('woocommerce_order_status_changed', [self::class, 'sync_sub_orders_status'], 10, 4);
    }

    public static function sync_sub_orders_status($order_id, $old_status, $new_status, $order)
    {
        remove_action('woocommerce_order_status_changed', [self::class, 'sync_sub_orders_status'], 10);
        
        $sub_orders = wc_get_orders([
            'parent_id' => $order_id,
            'limit'     => -1,
        ]);

        if (!empty($sub_orders)) {
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
        if (isset($values['menu_id'])) {
            $item->add_meta_data('_menu_id', $values['menu_id'], true);
        }
    }

    public static function split_order_by_menu($order_id, $posted_data, $order)
    {
        if (did_action('woocommerce_checkout_order_processed') > 1) return;

        $items = $order->get_items();
        $shipping_items = $order->get_items('shipping');
        $menus = [];

        foreach ($items as $item_id => $item) {
            $menu_id = (string)($item->get_meta('_menu_id') ?: 'default');
            if (!isset($menus[$menu_id])) $menus[$menu_id] = ['items' => [], 'shipping' => null];
            $menus[$menu_id]['items'][] = $item;
        }

        foreach ($shipping_items as $sh_item) {
            $label = $sh_item->get_name();
            foreach ($menus as $menu_id => &$data) {
                $menu_name = self::get_menu_name($menu_id);
                if ($menu_name && strpos($label, $menu_name) !== false) {
                    $data['shipping'] = $sh_item;
                    break;
                }
            }
        }
        unset($data);

        if (count($menus) <= 1) {
            self::save_session_to_order($order, array_key_first($menus));
            self::clear_all_session_data($menus);
            return;
        }

        $is_first = true;
        foreach ($menus as $menu_id => $data) {
            if ($is_first) {
                $sub_order = $order;
                $is_first = false;
                
                foreach ($items as $item_id => $item) {
                    $item_menu = (string)($item->get_meta('_menu_id') ?: 'default');
                    if ($item_menu != $menu_id) $sub_order->remove_item($item_id);
                }
                foreach ($shipping_items as $sh_id => $sh_item) {
                    if (!$data['shipping'] || $sh_id != $data['shipping']->get_id()) $sub_order->remove_item($sh_id);
                }
            } else {
                $sub_order = wc_create_order([
                    'status'        => 'pending',
                    'customer_id'   => $order->get_customer_id(),
                    'customer_note' => $order->get_customer_note(),
                    'created_via'   => 'split_order',
                ]);
                $sub_order->set_address($order->get_address('billing'), 'billing');
                $sub_order->set_address($order->get_address('shipping'), 'shipping');
                $sub_order->set_payment_method($order->get_payment_method());
                $sub_order->set_payment_method_title($order->get_payment_method_title());

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
                    $new_item->add_meta_data('_menu_id', $menu_id, true);
                    $sub_order->add_item($new_item);
                }
                if ($data['shipping']) {
                    $new_sh = new \WC_Order_Item_Shipping();
                    $new_sh->set_props([
                        'method_title' => $data['shipping']->get_method_title(),
                        'method_id'    => $data['shipping']->get_method_id(),
                        'instance_id'  => $data['shipping']->get_instance_id(),
                        'total'        => $data['shipping']->get_total(),
                    ]);
                    $sub_order->add_item($new_sh);
                }
                $sub_order->set_parent_id($order_id);
                $sub_order->set_status($order->get_status());
            }

            self::save_session_to_order($sub_order, $menu_id);
            $sub_order->calculate_totals();
            $sub_order->save();
        }

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
        
        if ($order_mode) $order->update_meta_data('_zippy_order_mode', $order_mode);
        if ($date) $order->update_meta_data('_zippy_date', $date);
        if ($time) $order->update_meta_data('_zippy_time', is_array($time) ? json_encode($time) : $time);
        if ($outlet) $order->update_meta_data('_zippy_outlet_name', $outlet);
    }

    private static function clear_all_session_data($menus)
    {
        if (!WC()->session) return;

        $keys = [
            'order_mode', 'date', 'time', 'outlet_id', 'outlet_name', 
            'outlet_address', 'delivery_address', 'postal', 
            'total_distance', 'shipping_fee', 'status_popup'
        ];

        foreach (array_keys($menus) as $menu_id) {
            $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
            foreach ($keys as $key) {
                WC()->session->set($key . $suffix, null);
            }
        }
        
        // Final fallback to clear global keys too
        foreach ($keys as $key) {
            WC()->session->set($key, null);
        }
        
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }
}
