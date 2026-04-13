<?php

namespace AiZippy\Order;

use AiZippy\Core\ViteAssets;

defined('ABSPATH') || exit;

/**
 * Order Mode Info Assets
 *
 * Enqueues Order Mode Info React app
 */
class OrderModeInfoAssets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        // Only enqueue if container exists on page
        if (!self::shouldEnqueue()) {
            return;
        }

        ViteAssets::enqueue(
            'ai-zippy-order-mode-info',
            'src/wp-content/themes/ai-zippy/src/js/order-mode-info/index.jsx'
        );

        // Pass config URL and nonce to JS
        wp_add_inline_script(
            'ai-zippy-order-mode-info',
            'window.orderModeInfo = ' . json_encode([
                'shopUrl' => home_url('/shop/'),
                'restNonce' => wp_create_nonce('wp_rest'),
            ]),
            'before'
        );

        // Ensure WC Store API nonce is available
        ViteAssets::enqueueTheme();
    }

    /**
     * Check if page should have order mode info
     */
    private static function shouldEnqueue(): bool
    {
        // Enqueue on all frontend pages
        return !is_admin();
    }
}