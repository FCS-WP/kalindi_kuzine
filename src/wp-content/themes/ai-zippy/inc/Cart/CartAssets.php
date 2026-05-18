<?php

namespace AiZippy\Cart;

defined('ABSPATH') || exit;

/**
 * Enqueue cart React app on WooCommerce cart page.
 */
class CartAssets
{
    /**
     * Register hooks.
     */
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Enqueue cart assets and disable core cart fragments to prevent loops.
     */
    public static function enqueue(): void
    {
        if (!is_cart() && !is_page('cart')) {
            return;
        }

        // Disable WC core cart fragments on our custom cart page
        // to prevent it from triggering full page reloads via {"reload": true}
        wp_dequeue_script('wc-cart-fragments');

        \AiZippy\Core\ViteAssets::enqueue(
            'ai-zippy-cart',
            'src/wp-content/themes/ai-zippy/src/js/cart/index.jsx'
        );
    }
}
