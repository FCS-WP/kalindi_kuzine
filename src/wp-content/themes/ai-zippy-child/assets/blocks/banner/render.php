<?php

/**
 * Server-side render for Hero Banner block.
 * Split-layer design with semi-circle overlay.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

$headline          = $attributes['headline'] ?? '';
$headline_desc     = $attributes['headlineDescription'] ?? '';
$brand_name        = $attributes['brandName'] ?? '';
$brand_description = $attributes['brandDescription'] ?? '';
$button_text       = $attributes['buttonText'] ?? '';
$button_url        = esc_url($attributes['buttonUrl'] ?? '#');
$bg_image_url      = esc_url($attributes['backgroundImageUrl'] ?? '');
$accent_color      = $attributes['accentColor'] ?? '#D4A574';
$headline_color    = $attributes['headlineColor'] ?? '#8B7355';
$brand_color       = $attributes['brandColor'] ?? '#D4A574';

$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; ?>>
	<!-- Top Image Layer -->
	<?php if ($bg_image_url) : ?>
		<div class="banner__top-image" style="background-image: url(<?php echo $bg_image_url; ?>)"></div>
	<?php else : ?>
		<div class="banner__top-image"></div>
	<?php endif; ?>

	<!-- Semi-circle Overlay with Headline -->
	<div class="banner__semicircle">
		<div class="banner__semicircle-content">
			<?php if ($headline) : ?>
				<h2 class="banner__headline" style="color: <?php echo esc_attr($headline_color); ?>">
					<?php echo nl2br(esc_html($headline)); ?>
				</h2>
			<?php endif; ?>

			<?php if ($headline_desc) : ?>
				<p class="banner__headline-desc"><?php echo esc_html($headline_desc); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Bottom Content Area -->
	<div class="banner__content">
		<?php if ($brand_name) : ?>
			<h3 class="banner__brand-name" style="color: <?php echo esc_attr($brand_color); ?>">
				<?php echo esc_html($brand_name); ?>
			</h3>
		<?php endif; ?>

		<?php if ($brand_description) : ?>
			<p class="banner__brand-desc"><?php echo esc_html($brand_description); ?></p>
		<?php endif; ?>

		<?php if ($button_text) : ?>
			<div class="banner__cta">
				<a href="<?php echo $button_url; ?>" class="banner__btn" style="background-color: <?php echo esc_attr($accent_color); ?>">
					<span><?php echo esc_html($button_text); ?></span>
				</a>
			</div>
		<?php endif; ?>
	</div>
</div>