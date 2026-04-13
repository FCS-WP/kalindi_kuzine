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

        register_rest_route(self::NAMESPACE, '/order-session/clear', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'clearSession'],
            'permission_callback' => '__return_true',
        ]);
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

        $data = [
            'order_mode'     => WC()->session->get('order_mode'),
            'date'           => WC()->session->get('date'),
            'time'           => WC()->session->get('time'),
            'outlet_name'    => WC()->session->get('outlet_name'),
            'outlet_address' => WC()->session->get('outlet_address'),
            'delivery_address' => WC()->session->get('delivery_address') ?: WC()->session->get('address_name'),
            'postal'         => WC()->session->get('postal'),
            'total_distance' => WC()->session->get('total_distance'),
            'shipping_fee'   => WC()->session->get('shipping_fee'),
            'status_popup'   => WC()->session->get('status_popup'),
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
        if (!function_exists('WC')) {
            return new WP_REST_Response(['success' => false, 'message' => 'WooCommerce not available'], 500);
        }

        // Initialize session if needed
        if (!WC()->session) {
            WC()->session = new \WC_Session_Handler();
            WC()->session->init();
        }

        // Initialize cart if needed
        if (!WC()->cart) {
            WC()->cart = new \WC_Cart();
        }

        // Empty the cart first
        WC()->cart->empty_cart();
        WC()->cart->calculate_totals();

        // Clear order session data
        WC()->session->set('order_mode', null);
        WC()->session->set('date', null);
        WC()->session->set('time', null);
        WC()->session->set('outlet_name', null);
        WC()->session->set('outlet_address', null);
        WC()->session->set('outlet_id', null);
        WC()->session->set('status_popup', null);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Session cleared and cart emptied',
        ], 200);
    }
}