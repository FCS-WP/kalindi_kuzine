<?php

defined('ABSPATH') || exit;

$items = $attributes['items'] ?? [];

$wrapper = get_block_wrapper_attributes(['class' => 'st']);
?>
<div <?php echo $wrapper; ?>>
	<div class="st__container">
		<!-- Horizontal timeline line -->
		<div class="st__timeline-line"></div>

		<!-- Timeline items -->
		<div class="st__items-wrapper">
			<?php foreach ($items as $index => $item) : ?>
				<div class="st__item">
					<!-- Icon/Marker -->
					<div class="st__marker">
						<div class="st__icon">
							<?php echo wp_kses_post($item['icon'] ?? '⭐'); ?>
						</div>
					</div>

					<!-- Content -->
					<div class="st__content">
						<?php if (!empty($item['title'])) : ?>
							<h3 class="st__title">
								<?php echo wp_kses_post($item['title']); ?>
							</h3>
						<?php endif; ?>

						<?php if (!empty($item['description'])) : ?>
							<p class="st__description">
								<?php echo wp_kses_post($item['description']); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
