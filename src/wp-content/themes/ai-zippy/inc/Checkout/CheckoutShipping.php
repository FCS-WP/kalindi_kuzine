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
        add_action('woocommerce_checkout_update_order_review', [self::class, 'calculateDistanceClassic']);
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'calculateDistance'], 10, 2);
        add_filter('woocommerce_package_rates', [self::class, 'addDistanceToLabels'], 100, 2);
        add_filter('woocommerce_store_api_cart_response', [self::class, 'extendCartResponse'], 10, 2);
    }

    public static function extendCartResponse($response, $cart): array
    {
        $distance_meters = WC()->session ? (float) WC()->session->get('total_distance') : 0;
        $distance_km = round($distance_meters / 1000, 2);
        $response['extensions']['zippy_booking'] = ['distance_km' => $distance_km];
        return $response;
    }

    /**
     * For Classic Checkout AJAX updates.
     */
    public static function calculateDistanceClassic($post_data): void
    {
        if (!WC()->session) return;

        $order_mode = WC()->session->get('order_mode');
        if ($order_mode === 'delivery') {
            // Re-trigger calculation if needed
            self::calculateCommon();
        }
    }

    /**
     * For Blocks Checkout (Store API) updates.
     */
    public static function calculateDistance($cart, $request): void
    {
        self::calculateCommon($request);
    }

    /**
     * Shared logic for distance calculation.
     */
    private static function calculateCommon($request = null): void
    {
        if (!WC()->session) return;

        $order_mode = WC()->session->get('order_mode');
        if ($order_mode === 'takeaway') {
            WC()->session->set('zippy_checkout_distance', 0);
            WC()->session->set('zippy_checkout_distance_meters', 0);
            return;
        }

        $distance_meters = (float) WC()->session->get('total_distance');

        // If distance is missing in session, try to calculate from address in request OR current customer
        if ($distance_meters <= 0) {
            $address = '';
            $postcode = '';

            if ($request && isset($request['shipping_address'])) {
                $address = $request['shipping_address']['address_1'] ?? '';
                $postcode = $request['shipping_address']['postcode'] ?? '';
            } elseif (WC()->customer) {
                $address = WC()->customer->get_shipping_address_1();
                $postcode = WC()->customer->get_shipping_postcode();
            }

            $origin = WC()->session->get('outlet_address');
            if (!empty($origin) && (!empty($address) || !empty($postcode))) {
                $distance_meters = self::getDistanceInMeters($origin, $address . ' ' . $postcode);
            }
        }

        if ($distance_meters > 0) {
            $distance_km = round($distance_meters / 1000, 2);
            WC()->session->set('zippy_checkout_distance', $distance_km);
            WC()->session->set('zippy_checkout_distance_meters', $distance_meters);
            WC()->session->set('billing_distance', $distance_km);
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

        $distance_meters = (float) WC()->session->get('total_distance');
        $distance_km = round($distance_meters / 1000, 2);
        $order_mode = WC()->session->get('order_mode');
        $fee = (float) WC()->session->get('shipping_fee');

        foreach ($rates as $rate_id => $rate) {
            $label_original = $rate->get_label();
            $label = strtolower($label_original);

            if ($order_mode === 'delivery') {
                // Match any label that contains 'shipping' or 'flat rate'
                if (strpos($label, 'shipping') !== false || strpos($label, 'flat rate') !== false) {
                    if ($distance_km > 0) {
                        $rate->set_label('Shipping Fee (' . $distance_km . 'km)');
                    } elseif ($fee > 0) {
                        // Fallback if distance is zero but fee exists
                        $rate->set_label('Shipping Fee');
                    }
                    
                    if ($fee > 0) {
                        $rate->set_cost($fee);
                    }
                }
                if (strpos($label, 'take away') !== false) {
                    unset($rates[$rate_id]);
                }
            } elseif ($order_mode === 'takeaway') {
                if (strpos($label, 'shipping') !== false || strpos($label, 'flat rate') !== false) {
                    unset($rates[$rate_id]);
                }
            }
        }

        return $rates;
    }
}
