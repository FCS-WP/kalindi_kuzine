<?php

namespace AiZippy\Checkout;

defined('ABSPATH') || exit;

/**
 * Handle custom order fields (Delivery/Takeaway details) in Admin.
 * This file is responsible for saving data from Session to Order Meta.
 */
class AdminOrderFields
{
    public static function register(): void
    {
        // Use woocommerce_checkout_create_order for Classic Checkout
        add_action('woocommerce_checkout_create_order', [self::class, 'saveZippyDataToOrder'], 10, 2);

        // Support for Blocks Checkout
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'saveZippyDataToOrderBlocks'], 10, 2);

        // Display fields in Admin Order Edit page
        add_action('woocommerce_admin_order_data_after_billing_address', [self::class, 'displayAdminOrderDetails'], 10, 1);
    }

    /**
     * Classic Checkout: Save session data directly to the Order object.
     * @param \WC_Order $order
     * @param array $data
     */
    public static function saveZippyDataToOrder($order, $data): void
    {
        self::syncSessionToOrder($order);
    }

    /**
     * Blocks Checkout: Save session data to the Order object.
     * @param \WC_Order $order
     * @param \WP_REST_Request $request
     */
    public static function saveZippyDataToOrderBlocks($order, $request): void
    {
        self::syncSessionToOrder($order);
    }

    /**
     * Core logic to sync WC Session to Order Meta.
     */
    private static function syncSessionToOrder($order): void
    {
        if (!WC()->session) return;

        $order_mode = WC()->session->get('order_mode');
        $date       = WC()->session->get('date');
        $time       = WC()->session->get('time');
        $outlet     = WC()->session->get('outlet_name');
        $outlet_addr = WC()->session->get('outlet_address');

        if ($order_mode) {
            $order->update_meta_data('_zippy_order_mode', $order_mode);
        }
        if ($date) {
            $order->update_meta_data('_zippy_date', $date);
        }
        if ($time) {
            $time_val = is_array($time) ? json_encode($time) : $time;
            $order->update_meta_data('_zippy_time', $time_val);
        }
        if ($outlet) {
            $order->update_meta_data('_zippy_outlet_name', $outlet);
        }
        if ($outlet_addr) {
            $order->update_meta_data('_zippy_outlet_address', $outlet_addr);
        }

        // Add a visible note for Admin
        if ($order_mode) {
            $note = sprintf(
                "Zippy Order: %s | Date: %s | Outlet: %s",
                ucfirst($order_mode),
                $date,
                $outlet
            );
            $order->add_order_note($note);
        }

        // No need to call $order->save() here inside create_order hook, 
        // WooCommerce will save the metadata automatically.
    }

    /**
     * Display the custom info in WC Admin Order details.
     */
    public static function displayAdminOrderDetails($order): void
    {
        $order_mode = $order->get_meta('_zippy_order_mode');
        $date       = $order->get_meta('_zippy_date');
        $time_raw   = $order->get_meta('_zippy_time');
        $outlet     = $order->get_meta('_zippy_outlet_name');

        if (!$order_mode) {
            echo '<div style="padding-top:10px; margin-top:10px; border-top: 1px solid #eee; color: #999;">';
            echo '<p>No Zippy Delivery info found for this order.</p>';
            echo '</div>';
            return;
        }

        $formatted_time = $time_raw;
        $time_data = json_decode($time_raw, true);
        if (is_array($time_data) && isset($time_data['from'])) {
            $formatted_time = "{$time_data['from']} - {$time_data['to']}";
        }

        echo '<div style="clear:both; padding: 12px; margin-top: 15px; border: 1px solid #d84315; background: #fff5f2; border-radius: 4px;">';
        echo '<h3 style="margin-top:0; color: #d84315; font-size: 14px; text-transform: uppercase;">' . __('Zippy Order Info', 'ai-zippy') . '</h3>';
        echo '<table style="width:100%; font-size: 13px;">';
        echo '<tr><td style="width:100px; font-weight:bold;">Mode:</td><td>' . ucfirst(esc_html($order_mode)) . '</td></tr>';
        echo '<tr><td style="font-weight:bold;">Outlet:</td><td>' . esc_html($outlet) . '</td></tr>';
        echo '<tr><td style="font-weight:bold;">Date:</td><td>' . esc_html($date) . '</td></tr>';
        echo '<tr><td style="font-weight:bold;">Time Slot:</td><td>' . esc_html($formatted_time) . '</td></tr>';
        echo '</table>';
        echo '</div>';
    }
}
