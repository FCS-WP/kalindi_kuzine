<?php

namespace AiZippyChild;

defined('ABSPATH') || exit;

/**
 * Register custom blocks for child theme.
 */
class BlockRegistration
{
	/**
	 * Register hooks.
	 */
	public static function register(): void
	{
		add_action('init', [self::class, 'registerBlocks']);
	}

	/**
	 * Register custom blocks from child theme assets/blocks directory.
	 * Blocks are built by wp-scripts into assets/blocks/
	 */
	public static function registerBlocks(): void
	{
		$blocks_dir = get_stylesheet_directory() . '/assets/blocks';

		if (!is_dir($blocks_dir)) {
			return;
		}

		foreach (glob($blocks_dir . '/*/block.json') as $block_json) {
			register_block_type(dirname($block_json));
		}
	}
}