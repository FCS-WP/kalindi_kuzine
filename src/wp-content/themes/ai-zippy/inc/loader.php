<?php

/**
 * Theme Autoloader & Bootstrap
 *
 * - PSR-4 autoloader for AiZippy\ namespace
 * - Registers all classes from inc/ subdirectories
 * - Procedural files in setup/ are loaded directly
 *
 * Namespace mapping:
 *   AiZippy\Core\*       → inc/Core/*.php
 *   AiZippy\Api\*        → inc/Api/*.php
 *   AiZippy\Hooks\*      → inc/Hooks/*.php
 *   AiZippy\Shop\*       → inc/Shop/*.php
 *   AiZippy\Cart\*       → inc/Cart/*.php
 *   AiZippy\Checkout\*   → inc/Checkout/*.php
 */

defined('ABSPATH') || exit;

// PSR-4 Autoloader: AiZippy\ → inc/
spl_autoload_register(function (string $class): void {
    $prefix = 'AiZippy\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = AI_ZIPPY_THEME_DIR . '/inc/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load procedural setup files (early boot)
foreach (glob(AI_ZIPPY_THEME_DIR . '/inc/setup/*.php') as $file) {
    require_once $file;
}

// ---- Bootstrap: register all modules ----

// Core
AiZippy\Core\ViteAssets::register();
AiZippy\Core\ThemeSetup::register();

// Hooks
AiZippy\Hooks\CacheInvalidation::register();

// API
add_action('rest_api_init', [AiZippy\Api\ProductFilterApi::class, 'register']);

// Shop
AiZippy\Shop\ShopAssets::register();

// Cart
AiZippy\Cart\CartAssets::register();

// Checkout
AiZippy\Checkout\CheckoutSettings::register();
AiZippy\Checkout\CheckoutShortcode::register();
AiZippy\Checkout\OrderConfirmationShortcode::register();
AiZippy\Checkout\CheckoutValidation::register();
AiZippy\Checkout\CheckoutAssets::register();
