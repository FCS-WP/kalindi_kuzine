<?php

/**
 * Server-side render for Promotions Grid block.
 * Displays promotion cards in a responsive grid or slider with image overlays.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

$section_title = $attributes['sectionTitle'] ?? '';
$is_hidden     = $attributes['isHidden'] ?? false;
$is_slider     = $attributes['isSlider'] ?? false;
$autoplay      = $attributes['autoplay'] ?? true;

if ($is_hidden && !is_admin() && !is_customize_preview()) {
	return;
}

// Fetch promotions from Custom Post Type
$promo_query = new WP_Query([
	'post_type'      => 'promotion',
	'posts_per_page' => 10,
	'post_status'    => 'publish',
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
]);

$promotions_data = [];
if ($promo_query->have_posts()) {
	while ($promo_query->have_posts()) {
		$promo_query->the_post();
		$promotions_data[] = [
			'id'       => get_the_ID(),
			'title'    => get_the_title(),
			'subtitle' => get_the_excerpt(), // Use excerpt as subtitle
			'imageUrl' => get_the_post_thumbnail_url(get_the_ID(), 'large'),
		];
	}
	wp_reset_postdata();
}

// Fallback to attributes if DB is empty (for migration/testing)
if (empty($promotions_data)) {
	$promotions_data = $attributes['promotions'] ?? [];
}

$wrapper_classes = ['promotions-grid'];
if ($is_slider) {
	$wrapper_classes[] = 'promotions-grid--slider';
}

$wrapper_attributes = get_block_wrapper_attributes(['class' => implode(' ', $wrapper_classes)]);

$swiper_config = wp_json_encode([
	'autoplay' => $autoplay,
	'count'    => count($promotions_data)
]);
?>

<div <?php echo $wrapper_attributes; ?> <?php echo $is_slider ? 'data-swiper-config="' . esc_attr($swiper_config) . '"' : ''; ?>>
	<?php if ($section_title) : ?>
		<h2 class="promotions-grid__title">
			<?php echo esc_html($section_title); ?>
		</h2>
	<?php endif; ?>

	<div class="<?php echo $is_slider ? 'swiper promotions-grid__swiper' : 'promotions-grid__grid'; ?>">
		<?php if ($is_slider) : ?><div class="swiper-wrapper"><?php endif; ?>
			<?php foreach ($promotions_data as $promo) : ?>
				<?php
				$image_url = esc_url($promo['imageUrl'] ?? '');
				$title     = esc_html($promo['title'] ?? '');
				$subtitle  = esc_html($promo['subtitle'] ?? '');
				?>
				<div class="promotions-grid__card <?php echo $is_slider ? 'swiper-slide' : ''; ?>">
					<?php if ($image_url) : ?>
						<div
							class="promotions-grid__card-image"
							style="background-image: url(<?php echo $image_url; ?>);"></div>
					<?php else : ?>
						<div class="promotions-grid__card-image" style="background: linear-gradient(135deg, #e8d5c4 0%, #d4c4b0 100%);"></div>
					<?php endif; ?>

					<div class="promotions-grid__card-overlay">
						<div class="promotions-grid__card-content">
							<?php if ($title) : ?>
								<h3 class="promotions-grid__card-title">
									<?php echo $title; ?>
								</h3>
							<?php endif; ?>

							<?php if ($subtitle) : ?>
								<p class="promotions-grid__card-subtitle">
									<?php echo $subtitle; ?>
								</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php if ($is_slider) : ?></div><?php endif; ?>
	</div>
	
	<?php if ($is_slider) : ?>
		<div class="promotions-grid__pagination swiper-pagination"></div>
		<div class="promotions-grid__nav">
			<div class="promotions-grid__nav-prev swiper-button-prev"></div>
			<div class="promotions-grid__nav-next swiper-button-next"></div>
		</div>
	<?php endif; ?>
</div>