<?php

/**
 * Server-side render for Promotions Grid block.
 * Displays promotion cards in a responsive grid with image overlays.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

$section_title = $attributes['sectionTitle'] ?? '';
$promotions    = $attributes['promotions'] ?? [];

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; ?>>
	<?php if ($section_title) : ?>
		<h2 class="promotions-grid__title">
			<?php echo esc_html($section_title); ?>
		</h2>
	<?php endif; ?>

	<div class="promotions-grid__grid">
		<?php foreach ($promotions as $promo) : ?>
			<?php
			$image_url = esc_url($promo['imageUrl'] ?? '');
			$title     = esc_html($promo['title'] ?? '');
			$subtitle  = esc_html($promo['subtitle'] ?? '');
			?>
			<div class="promotions-grid__card">
				<?php if ($image_url) : ?>
					<div
						class="promotions-grid__card-image"
						style="background-image: url(<?php echo $image_url; ?>);"
					></div>
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
	</div>
</div>