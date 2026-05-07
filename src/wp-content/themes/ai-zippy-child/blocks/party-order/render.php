<?php

/**
 * Party Order Block - Server-side Render
 */

$limit = isset($attributes['limit']) ? intval($attributes['limit']) : 8;
$columns = isset($attributes['columns']) ? intval($attributes['columns']) : 3;

// Enqueue via ViteAssets
if (class_exists('\AiZippy\Core\ViteAssets')) {
	\AiZippy\Core\ViteAssets::enqueueTheme();

	// Enqueue party-order React app from child theme
	\AiZippy\Core\ViteAssets::enqueue(
		'ai-zippy-party-order',
		'src/wp-content/themes/ai-zippy-child/src/js/party-order/index.jsx'
	);
}

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'wp-block-ai-zippy-child-party-order']);
?>

<div <?php echo $wrapper_attributes; ?>>
	<div id="ai-zippy-party-order" 
         data-limit="<?php echo esc_attr($limit); ?>" 
         data-columns="<?php echo esc_attr($columns); ?>">
    </div>
</div>
