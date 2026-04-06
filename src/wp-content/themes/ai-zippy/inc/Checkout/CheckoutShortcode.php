<?php

namespace AiZippy\Checkout;

defined('ABSPATH') || exit;

/**
 * [ai_zippy_checkout] shortcode.
 *
 * Renders either the React checkout mount point or the
 * WooCommerce default checkout, based on admin setting.
 */
class CheckoutShortcode
{
    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode('ai_zippy_checkout', [self::class, 'render']);
    }

    /**
     * Render the checkout output.
     */
    public static function render(): string
    {
        if (CheckoutSettings::isReact()) {
            return '<div id="ai-zippy-checkout" data-cart-url="/cart" data-shop-url="/shop"></div>';
        }

        // Render the WooCommerce Checkout block (used by block/FSE themes)
        $block_markup = '<!-- wp:woocommerce/checkout /-->';
        $output = do_blocks($block_markup);

        // Fallback to classic shortcode if block output is empty
        if (empty(trim($output))) {
            $output = do_shortcode('[woocommerce_checkout]');
        }

        return self::cleanOutput($output);
    }

    /**
     * Remove empty <p></p> and stray <br> injected by wpautop.
     */
    private static function cleanOutput(string $html): string
    {
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        $html = preg_replace('/(<\/(?:span|div|svg|button|address|strong|select|input|table|form)>)\s*<br\s*\/?>/', '$1', $html);
        $html = preg_replace('/<br\s*\/?>\s*(<(?:span|div|svg|button|address|strong|a|select|input|table|form)\b)/', '$1', $html);
        $html = preg_replace('/<p>\s*<!--/', '<!--', $html);
        $html = preg_replace('/-->\s*<\/p>/', '-->', $html);
        return $html;
    }
}
