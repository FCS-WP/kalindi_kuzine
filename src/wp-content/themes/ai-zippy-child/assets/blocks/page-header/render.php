<?php
/**
 * Render for Page Header Banner.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

$title           = $attributes['title'] ?? get_the_title();
$subtitle        = $attributes['subtitle'] ?? '';
$backgroundColor = $attributes['backgroundColor'] ?? '#cc5d33';
$textColor       = $attributes['textColor'] ?? '#ffffff';

$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'page-header-banner',
	'style' => "background-color: $backgroundColor; color: $textColor;"
]);
?>

<div <?php echo $wrapper_attributes; ?>>
	<div class="page-header-banner__container">
		<?php if ( function_exists( 'woocommerce_breadcrumb' ) ) : ?>
			<div class="page-header-banner__breadcrumbs">
				<?php woocommerce_breadcrumb([
					'delimiter' => ' / ',
					'wrap_before' => '<nav class="woocommerce-breadcrumb">',
					'wrap_after' => '</nav>',
					'before' => '',
					'after' => '',
				]); ?>
			</div>
		<?php endif; ?>

		<h1 class="page-header-banner__title">
			<?php echo esc_html( $title ); ?>
		</h1>

		<?php if ( $subtitle ) : ?>
			<p class="page-header-banner__subtitle">
				<?php echo esc_html( $subtitle ); ?>
			</p>
		<?php endif; ?>
	</div>
</div>
