<?php

namespace AiZippy\Shop;

defined('ABSPATH') || exit;

/**
 * Enqueue shop filter React app on WooCommerce pages.
 */
class ShopAssets
{
    /**
     * Register hooks.
     */
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Enqueue shop filter assets.
     */
    public static function enqueue(): void
    {
        if (!is_shop() && !is_product_taxonomy()) {
            return;
        }

        \AiZippy\Core\ViteAssets::enqueue(
            'ai-zippy-shop-filter',
            'src/wp-content/themes/ai-zippy/src/js/shop-filter/index.jsx'
        );
    }
}
