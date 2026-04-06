<?php

namespace AiZippy\Checkout;

defined('ABSPATH') || exit;

/**
 * Enqueue checkout assets based on admin template selection.
 *
 * - "react"       → Enqueue Vite-built React checkout app
 * - "woocommerce" → Enqueue WC default checkout styles only
 */
class CheckoutAssets
{
    /**
     * Register hooks.
     */
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_az_update_checkout_qty', [self::class, 'ajaxUpdateQty']);
        add_action('wp_ajax_nopriv_az_update_checkout_qty', [self::class, 'ajaxUpdateQty']);
    }

    /**
     * AJAX handler: update cart item quantity from checkout sidebar.
     */
    public static function ajaxUpdateQty(): void
    {
        check_ajax_referer('az-checkout-qty', 'security');

        $cart_key = sanitize_text_field($_POST['cart_key'] ?? '');
        $quantity = absint($_POST['quantity'] ?? 0);

        if (empty($cart_key)) {
            wp_send_json_error('Invalid cart key');
        }

        if ($quantity === 0) {
            WC()->cart->remove_cart_item($cart_key);
        } else {
            WC()->cart->set_quantity($cart_key, $quantity);
        }

        wp_send_json_success();
    }

    /**
     * Enqueue checkout assets on checkout page only.
     */
    public static function enqueue(): void
    {
        if (!is_checkout() && !is_page('checkout')) {
            return;
        }

        if (CheckoutSettings::isReact()) {
            self::enqueueReactCheckout();
        } else {
            self::enqueueWcCheckout();
        }
    }

    /**
     * Enqueue React checkout app.
     */
    private static function enqueueReactCheckout(): void
    {
        \AiZippy\Core\ViteAssets::enqueue(
            'ai-zippy-checkout',
            'src/wp-content/themes/ai-zippy/src/js/checkout/index.jsx'
        );

        wp_localize_script('ai-zippy-checkout', 'aiZippyCheckout', [
            'paymentGateways'   => self::getPaymentGateways(),
            'shippingEnabled'   => 'yes' === get_option('woocommerce_calc_shipping', 'yes'),
            'shipToDestination' => get_option('woocommerce_ship_to_destination', 'shipping'),
            'customer'          => self::getCustomerData(),
        ]);
    }

    /**
     * Enqueue WooCommerce default checkout styles.
     */
    private static function enqueueWcCheckout(): void
    {
        \AiZippy\Core\ViteAssets::enqueue(
            'ai-zippy-wc-checkout',
            'src/wp-content/themes/ai-zippy/src/scss/wc-checkout-entry.scss'
        );
    }

    /**
     * Get enabled WooCommerce payment gateways.
     */
    private static function getPaymentGateways(): array
    {
        $gateways = [];

        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return $gateways;
        }

        foreach (WC()->payment_gateways()->get_available_payment_gateways() as $gateway) {
            $gateways[] = [
                'id'          => $gateway->id,
                'title'       => $gateway->get_title(),
                'description' => $gateway->get_description(),
            ];
        }

        return $gateways;
    }

    /**
     * Get logged-in customer data for form pre-fill.
     */
    private static function getCustomerData(): array
    {
        if (!is_user_logged_in() || !function_exists('WC') || !WC()->customer) {
            return [];
        }

        $c = WC()->customer;

        return [
            'firstName' => $c->get_billing_first_name(),
            'lastName'  => $c->get_billing_last_name(),
            'email'     => $c->get_billing_email(),
            'phone'     => $c->get_billing_phone(),
            'billing'   => [
                'address_1' => $c->get_billing_address_1(),
                'address_2' => $c->get_billing_address_2(),
                'city'      => $c->get_billing_city(),
                'state'     => $c->get_billing_state(),
                'postcode'  => $c->get_billing_postcode(),
                'country'   => $c->get_billing_country() ?: 'SG',
                'company'   => $c->get_billing_company(),
            ],
        ];
    }
}
