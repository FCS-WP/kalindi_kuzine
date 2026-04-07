<?php

namespace AiZippy\Shop;

use AiZippy\Core\ViteAssets;

defined('ABSPATH') || exit;

/**
 * Most Ordered Products Assets
 * 
 * Enqueues Most Ordered React app and styles
 * Mounts on homepage or custom template
 */
class MostOrderedAssets
{
	public static function register(): void
	{
		// Enqueue on pages that have the most-ordered container
		add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
	}

	public static function enqueue(): void
	{
		// Only enqueue if container exists on page
		if (!self::shouldEnqueue()) {
			return;
		}

		ViteAssets::enqueue(
			'ai-zippy-most-ordered',
			'src/wp-content/themes/ai-zippy/src/js/most-ordered/index.jsx'
		);

		// Pass WC Store API nonce
		ViteAssets::enqueueTheme();
	}

	/**
	 * Check if page should have most-ordered
	 * Currently enqueues on homepage
	 */
	private static function shouldEnqueue(): bool
	{
		// Enqueue on homepage
		if (is_front_page() || is_home()) {
			return true;
		}

		// Can also check for custom template or shortcode if needed
		// e.g., check page content for a specific block/string

		return false;
	}
}
