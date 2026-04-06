<?php

namespace AiZippy\Core;

defined('ABSPATH') || exit;

/**
 * Theme setup: supports, blocks, block categories.
 */
class ThemeSetup
{
    /**
     * Register hooks.
     */
    public static function register(): void
    {
        add_action('after_setup_theme', [self::class, 'setup']);
        add_action('init', [self::class, 'registerBlocks']);
        add_filter('block_categories_all', [self::class, 'blockCategories']);
    }

    /**
     * Theme supports.
     */
    public static function setup(): void
    {
        add_theme_support('wp-block-styles');
        add_theme_support('editor-styles');
        add_theme_support('woocommerce');
        add_theme_support('responsive-embeds');
    }

    /**
     * Register custom blocks from assets/blocks (wp-scripts build output).
     */
    public static function registerBlocks(): void
    {
        $blocks_dir = AI_ZIPPY_THEME_DIR . '/assets/blocks';

        if (!is_dir($blocks_dir)) {
            return;
        }

        foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
            register_block_type(dirname($block_json));
        }
    }

    /**
     * Register custom block category.
     */
    public static function blockCategories(array $categories): array
    {
        array_unshift($categories, [
            'slug'  => 'ai-zippy',
            'title' => 'AI Zippy',
            'icon'  => 'star-filled',
        ]);

        return $categories;
    }
}
