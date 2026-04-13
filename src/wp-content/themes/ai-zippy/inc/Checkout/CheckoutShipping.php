<?php

namespace AiZippy\Checkout;

defined('ABSPATH') || exit;

use Zippy_Booking\Src\Services\One_Map_Api;

/**
 * Handle distance-based shipping calculation at checkout.
 */
class CheckoutShipping
{
    public static function register(): void
    {
        // Hook into Store API cart update to calculate distance
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'calculateDistance'], 10, 2);
        
        // Filter shipping rates to add distance info to labels
        add_filter('woocommerce_package_rates', [self::class, 'addDistanceToLabels'], 100, 2);

        // Add distance to Store API response
        add_filter('woocommerce_store_api_cart_response', [self::class, 'extendCartResponse'], 10, 2);
    }

    /**
     * Add distance info to the React checkout data.
     */
    public static function extendCartResponse($response, $cart): array
    {
        $distance_meters = WC()->session ? (float) WC()->session->get('total_distance') : 0;
        $distance_km = round($distance_meters / 1000, 2);
        
        $response['extensions']['zippy_booking'] = [
            'distance_km' => $distance_km
        ];

        return $response;
    }

    /**
     * Calculate distance using OneMap when address changes in Store API.
     */
    public static function calculateDistance($cart, $request): void
    {
        if (!WC()->session) return;

        // Check order mode
        $order_mode = WC()->session->get('order_mode');
        
        // Takeaway = freeshipping, no distance calculation needed
        if ($order_mode === 'takeaway') {
            WC()->session->set('zippy_checkout_distance', 0);
            WC()->session->set('zippy_checkout_distance_meters', 0);
            WC()->session->set('billing_distance', 0);
            return;
        }

        // Try to get distance from session first (if already calculated during add to cart)
        $distance_meters = (float) WC()->session->get('total_distance');
        
        // If not in session, calculate it using the address from request
        if ($distance_meters <= 0) {
            $billing_address = $request['billing_address'] ?? [];
            $address_text = $billing_address['address_1'] ?? '';
            $postcode = $billing_address['postcode'] ?? '';
            $full_address = $address_text . ' ' . $postcode;

            $origin = WC()->session->get('outlet_address');
            if (!empty($origin) && (!empty($address_text) || !empty($postcode))) {
                $distance_meters = self::getDistanceInMeters($origin, $full_address);
            }
        }
        
        if ($distance_meters > 0) {
            $distance_km = round($distance_meters / 1000, 2);
            WC()->session->set('zippy_checkout_distance', $distance_km);
            WC()->session->set('zippy_checkout_distance_meters', $distance_meters);
            // Also store it so Zippy_Handle_Shipping can find it later
            WC()->session->set('billing_distance', $distance_km);
        }
    }

    /**
     * Get distance between two addresses via OneMap (Returns meters).
     */
    private static function getDistanceInMeters($from, $to): float
    {
        $coord_from = self::getCoords($from);
        $coord_to = self::getCoords($to);
        if (!$coord_from || !$coord_to) return 0.0;

        $params = [
            'start' => $coord_from['lat'] . ',' . $coord_from['lng'],
            'end'   => $coord_to['lat'] . ',' . $coord_to['lng'],
            'routeType' => 'drive',
        ];

        $res = One_Map_Api::get('/api/public/routingsvc/route', $params);
        return isset($res['route_summary']['total_distance']) ? (float) $res['route_summary']['total_distance'] : 0.0;
    }

    private static function getCoords($address): ?array
    {
        $res = One_Map_Api::get('/api/common/elastic/search', [
            'searchVal' => $address,
            'returnGeom' => 'Y',
            'getAddrDetails' => 'Y'
        ]);

        if (!empty($res['results'][0])) {
            return [
                'lat' => $res['results'][0]['LATITUDE'],
                'lng' => $res['results'][0]['LONGITUDE']
            ];
        }
        return null;
    }

    public static function addDistanceToLabels($rates, $package): array
    {
        if (!WC()->session) return $rates;

        $distance_meters = (float) WC()->session->get('total_distance');
        $distance_km = round($distance_meters / 1000, 2);
        $order_mode = WC()->session->get('order_mode'); 
        
        // Get fee from session (priority) or recalculate
        $fee = (float) WC()->session->get('shipping_fee');
        $outlet_id = WC()->session->get('outlet_id');
        
        if ($fee <= 0 && $order_mode === 'delivery' && $outlet_id && class_exists('\Zippy_Booking\Src\Services\Zippy_Handle_Shipping')) {
            global $wpdb;
            $table = $wpdb->prefix . 'zippy_addons_shipping_config';
            $config = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE outlet_id = %s", $outlet_id));
            if ($config) {
                $shipping_service = '\Zippy_Booking\Src\Services\Zippy_Handle_Shipping';
                $fee = (float) $shipping_service::get_fee_from_config(maybe_unserialize($config->minimum_order_to_delivery), $distance_km);
            }
        }

        foreach ($rates as $rate_id => $rate) {
            $label = $rate->get_label();

            // Filter logic:
            if ($order_mode === 'delivery' && strpos(strtolower($label), 'take away') !== false) {
                unset($rates[$rate_id]);
                continue;
            }
            if ($order_mode === 'takeaway' && strpos(strtolower($label), 'shipping fee') !== false) {
                unset($rates[$rate_id]);
                continue;
            }

            // Clean up label (Aggressively)
            $label = html_entity_decode($label, ENT_QUOTES, 'UTF-8');
            $label = preg_replace('/[^a-zA-Z0-9\s]/u', '', $label); // Keep only letters, numbers and spaces
            $label = trim($label);

            if ($distance_km > 0) {
                $rate->set_label($label . ': ' . $distance_km . 'km');
            } else {
                $rate->set_label($label);
            }

            // OVERRIDE COST: If we have a calculated fee, apply it to the shipping rate
            if ($order_mode === 'delivery' && $fee > 0 && strpos(strtolower($label), 'shipping fee') !== false) {
                $rate->set_cost($fee);
            }
        }

        return $rates;
    }
}
