<?php

/**
 * Most Ordered Block - Server-side Render
 * 
 * Renders the container that React mounts into
 */

$limit = isset($attributes['limit']) ? intval($attributes['limit']) : 4;

// Enqueue via ViteAssets (proper manifest-based loading)
if (class_exists('\AiZippy\Core\ViteAssets')) {
	// Enqueue theme JS (contains WC Store API nonce)
	\AiZippy\Core\ViteAssets::enqueueTheme();

	// Enqueue most-ordered React app
	\AiZippy\Core\ViteAssets::enqueue(
		'ai-zippy-most-ordered',
		'src/wp-content/themes/ai-zippy/src/js/most-ordered/index.jsx'
	);
}

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'wp-block-ai-zippy-child-most-ordered']);
?>

<div <?php echo $wrapper_attributes; ?>>
	<div id="ai-zippy-most-ordered" data-limit="<?php echo esc_attr($limit); ?>"></div>
</div>