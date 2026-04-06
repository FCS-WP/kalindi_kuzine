<?php

/**
 * AI Zippy Child Theme Functions
 *
 * Add project-specific customizations here.
 * The parent theme (ai-zippy) handles Vite assets and core setup.
 */

defined('ABSPATH') || exit;

/**
 * Enqueue child theme styles after parent.
 */
function ai_zippy_child_enqueue_assets(): void
{
    // Child theme custom styles (only if file exists)
    $child_css = get_stylesheet_directory() . '/assets/dist/css/style.css';

    if (file_exists($child_css)) {
        wp_enqueue_style(
            'ai-zippy-child-style',
            get_stylesheet_directory_uri() . '/assets/dist/css/style.css',
            ['ai-zippy-theme-css-0'],
            filemtime($child_css)
        );
    }
}
add_action('wp_enqueue_scripts', 'ai_zippy_child_enqueue_assets', 20);
