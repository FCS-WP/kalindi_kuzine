<?php

/**
 * AI Zippy Child Theme Functions
 *
 * Add project-specific customizations here.
 * The parent theme (ai-zippy) handles Vite assets and core setup.
 */

defined('ABSPATH') || exit;

// Include child theme classes
require_once get_stylesheet_directory() . '/inc/BlockRegistration.php';

// Register child theme blocks
\AiZippyChild\BlockRegistration::register();

/**
 * Enqueue child theme styles after parent.
 * Child theme SCSS is built by Vite into parent theme dist/css/child-style.css
 */
function ai_zippy_child_enqueue_assets(): void
{
    // Child theme custom styles (outputs to parent theme dist as child-style.css)
    $child_css = get_template_directory() . '/assets/dist/css/child-style.css';

    if (file_exists($child_css)) {
        wp_enqueue_style(
            'ai-zippy-child-style',
            get_template_directory_uri() . '/assets/dist/css/child-style.css',
            ['ai-zippy-theme-css-0'],
            filemtime($child_css)
        );
    }

    // About Us page specific styles
    if (is_page('about-us')) {
        $about_css = get_template_directory() . '/assets/dist/css/child-about-us.css';
        if (file_exists($about_css)) {
            wp_enqueue_style(
                'ai-zippy-about-us-style',
                get_template_directory_uri() . '/assets/dist/css/child-about-us.css',
                ['ai-zippy-child-style'],
                filemtime($about_css)
            );
        }
    }

    // Blog page specific styles
    if (is_page('blog') || is_home()) {
        $blog_css = get_template_directory() . '/assets/dist/css/child-blog.css';
        if (file_exists($blog_css)) {
            wp_enqueue_style(
                'ai-zippy-blog-style',
                get_template_directory_uri() . '/assets/dist/css/child-blog.css',
                ['ai-zippy-child-style'],
                filemtime($blog_css)
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'ai_zippy_child_enqueue_assets', 20);
