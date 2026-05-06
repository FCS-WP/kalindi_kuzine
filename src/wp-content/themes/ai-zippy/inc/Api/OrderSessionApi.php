<?php

namespace AiZippy\Api;

use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * REST API: Order Session
 *
 * Endpoints:
 *   GET /wp-json/ai-zippy/v1/order-session — Get session info for order mode display
 *   POST /wp-json/ai-zippy/v1/order-session/clear — Clear session and reset cart
 */
class OrderSessionApi
{
    const NAMESPACE = 'ai-zippy/v1';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoute']);
    }

    public static function registerRoute(): void
    {
        register_rest_route(self::NAMESPACE, '/order-session', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'getSessionInfo'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/order-session/grouped-cart', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'getGroupedCart'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/order-session/clear', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'clearSession'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get cart items grouped by menu_id with session info for each.
     */
    public static function getGroupedCart(WP_REST_Request $request): WP_REST_Response
    {
        if (!function_exists('WC')) {
            return new WP_REST_Response([], 200);
        }

        if (null === WC()->cart) {
            wc_load_cart();
        }

        if (!WC()->cart) {
            return new WP_REST_Response([], 200);
        }

        $cart = WC()->cart->get_cart();
        $grouped = [];

        foreach ($cart as $item_key => $item) {
            $menu_id = isset($item['menu_id']) ? $item['menu_id'] : 'default';
            
            if (!isset($grouped[$menu_id])) {
                $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
                
                $grouped[$menu_id] = [
                    'menu_id' => $menu_id,
                    'session' => [
                        'order_mode'     => WC()->session->get('order_mode' . $suffix) ?: null,
                        'date'           => WC()->session->get('date' . $suffix) ?: null,
                        'time'           => WC()->session->get('time' . $suffix) ?: null,
                        'outlet_name'    => WC()->session->get('outlet_name' . $suffix) ?: null,
                        'outlet_address' => WC()->session->get('outlet_address' . $suffix) ?: null,
                    ],
                    'items' => []
                ];
            }

            $product = $item['data'];
            $grouped[$menu_id]['items'][] = [
                'key'      => $item_key,
                'id'       => $item['product_id'],
                'name'     => $product->get_name(),
                'quantity' => $item['quantity'],
                'price'    => $item['line_total'],
                'image'    => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            ];
        }

        return new WP_REST_Response(array_values($grouped), 200);
    }

    /**
     * Get session info from WooCommerce session.
     */
    public static function getSessionInfo(WP_REST_Request $request): WP_REST_Response
    {
        if (!function_exists('WC')) {
            return new WP_REST_Response([], 200);
        }

        // Ensure session is initialized
        if (WC()->session === null) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
        }

        // If session is still empty, try to load from cookie
        if (is_null(WC()->session->get_customer_id())) {
            $session_cookie = $_COOKIE['wp_woocommerce_session_' . COOKIEHASH] ?? '';
            if (!empty($session_cookie)) {
                $session_data = explode('||', $session_cookie);
                $customer_id = $session_data[0];
                WC()->session->init_session_cookie();
            }
        }

        $menu_id = $request->get_param('menu_id');
        $suffix = $menu_id ? '_' . $menu_id : '';

        $data = [
            'order_mode'     => WC()->session->get('order_mode' . $suffix),
            'date'           => WC()->session->get('date' . $suffix),
            'time'           => WC()->session->get('time' . $suffix),
            'outlet_name'    => WC()->session->get('outlet_name' . $suffix),
            'outlet_address' => WC()->session->get('outlet_address' . $suffix),
            'delivery_address' => WC()->session->get('delivery_address' . $suffix) ?: WC()->session->get('address_name' . $suffix),
            'postal'         => WC()->session->get('postal' . $suffix),
            'total_distance' => WC()->session->get('total_distance' . $suffix),
            'shipping_fee'   => WC()->session->get('shipping_fee' . $suffix),
            'status_popup'   => WC()->session->get('status_popup' . $suffix),
        ];

        // Filter out null values
        $data = array_filter($data, fn($value) => $value !== null);

        return new WP_REST_Response($data, 200);
    }

    /**
     * Clear session data and empty cart.
     */
    public static function clearSession(WP_REST_Request $request): WP_REST_Response
    {
        if (!function_exists('WC') || !WC()->session) {
            return new WP_REST_Response(['success' => false, 'message' => 'WC Session not found'], 200);
        }

        $menu_id = $request->get_param('menu_id');
        $suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';

        // Keys to clear
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

        foreach ($keys as $key) {
            WC()->session->set($key . $suffix, null);
        }

        // Also remove items from cart with this menu_id
        if (null === WC()->cart) {
            wc_load_cart();
        }

        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $item_menu_id = $cart_item['menu_id'] ?? 'default';
                
                // If clearing specific menu, or clearing everything (if menu_id is not provided)
                if ($menu_id === null || $item_menu_id == $menu_id || (empty($menu_id) && $item_menu_id == 'default')) {
                    WC()->cart->remove_cart_item($cart_item_key);
                }
            }
            WC()->cart->calculate_totals();
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Session and cart items cleared',
            'menu_id' => $menu_id
        ], 200);
    }
}