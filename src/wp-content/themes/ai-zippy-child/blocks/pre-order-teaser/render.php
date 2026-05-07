<?php
/**
 * Pre-order Teaser Block Template.
 *
 * @param array $attributes The block attributes.
 */

$title          = $attributes['title'] ?? 'Fresh & Healthy Meals, <span>Pre-ordered for You</span>';
$description    = $attributes['description'] ?? 'Plan your meals ahead and enjoy fresh, home-cooked food delivered to your door.';
$button_label   = $attributes['buttonLabel'] ?? 'Order Now';
$button_url     = $attributes['buttonUrl'] ?? '/pre-order';
$image_url      = $attributes['imageUrl'] ?? '';
$overlay_opacity = $attributes['overlayOpacity'] ?? 0.6;
$min_height     = $attributes['minHeight'] ?? 'auto';

$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'pre-order-teaser',
]);
?>

<section <?php echo $wrapper_attributes; ?>>
	<div class="pre-order-teaser__container">
		<div class="pre-order-teaser__banner">
			<div class="pre-order-teaser__text">
				<?php if ($title) : ?>
					<h2 class="pre-order-teaser__title"><?php echo wp_kses($title, ['span' => []]); ?></h2>
				<?php endif; ?>

				<?php if ($description) : ?>
					<p class="pre-order-teaser__description"><?php echo wp_kses_post($description); ?></p>
				<?php endif; ?>

				<?php if ($button_label && $button_url) : ?>
					<div class="pre-order-teaser__actions">
						<a href="<?php echo esc_url($button_url); ?>" class="pre-order-teaser__button">
							<span><?php echo esc_html($button_label); ?></span>
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</a>
					</div>
				<?php endif; ?>
			</div>

			<div class="pre-order-teaser__image">
				<?php if ($image_url) : ?>
					<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>">
				<?php else : ?>
					<div class="pre-order-teaser__image-placeholder"></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
