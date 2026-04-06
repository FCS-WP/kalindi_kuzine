<?php

/**
 * Server-side render for Product Showcase block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

defined('ABSPATH') || exit;

$heading        = $attributes['heading'] ?? '';
$columns        = (int) ($attributes['columns'] ?? 4);
$rows           = (int) ($attributes['rows'] ?? 2);
$category       = sanitize_text_field($attributes['category'] ?? '');
$brand          = sanitize_text_field($attributes['brand'] ?? '');
$display_style  = $attributes['displayStyle'] ?? 'grid';
$orderby        = $attributes['orderby'] ?? 'date';
$show_sale      = $attributes['showSaleBadge'] ?? true;
$show_rating    = $attributes['showRating'] ?? true;
$show_cart      = $attributes['showAddToCart'] ?? true;
$autoplay       = $attributes['autoplay'] ?? false;
$autoplay_delay = (int) ($attributes['autoplayDelay'] ?? 5000);
$total_items    = $columns * $rows;

// Query products
$args = [
    'status'  => 'publish',
    'limit'   => $total_items,
    'orderby' => $orderby,
    'order'   => $orderby === 'price' ? 'ASC' : 'DESC',
];

if (!empty($category)) {
    $args['category'] = [$category];
}

if (!empty($brand)) {
    $args['tax_query'] = [
        [
            'taxonomy' => 'pa_brand',
            'field'    => 'slug',
            'terms'    => [$brand],
        ],
    ];
}

$products = wc_get_products($args);

if (empty($products)) {
    return;
}

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'ps ps--' . $display_style,
]);

// Slider data attributes
$slider_data = '';
if ($display_style === 'slider') {
    $slider_config = wp_json_encode([
        'columns'       => $columns,
        'autoplay'      => $autoplay,
        'autoplayDelay' => $autoplay_delay,
        'rows'          => $rows,
    ]);
    $slider_data = ' data-swiper-config="' . esc_attr($slider_config) . '"';
}
?>

<div <?php echo $wrapper_attributes; ?><?php echo $slider_data; ?>>

    <?php if (!empty($heading)) : ?>
        <div class="ps__header">
            <h2 class="ps__heading"><?php echo esc_html($heading); ?></h2>
            <?php if ($display_style === 'slider') : ?>
                <div class="ps__nav">
                    <button class="ps__nav-btn ps__nav-prev" aria-label="Previous">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button class="ps__nav-btn ps__nav-next" aria-label="Next">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 6 15 12 9 18"/></svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($display_style === 'slider') : ?>
        <div class="swiper ps__swiper">
            <div class="swiper-wrapper">
                <?php foreach ($products as $product) : ?>
                    <div class="swiper-slide">
                        <?php ai_zippy_render_product_card($product, $show_sale, $show_rating, $show_cart); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination ps__pagination"></div>
        </div>
    <?php else : ?>
        <div class="ps__grid" style="grid-template-columns: repeat(<?php echo $columns; ?>, 1fr);">
            <?php foreach ($products as $product) : ?>
                <?php ai_zippy_render_product_card($product, $show_sale, $show_rating, $show_cart); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php

/**
 * Render a single product card (shared between grid and slider).
 */
if (!function_exists('ai_zippy_render_product_card')) :
function ai_zippy_render_product_card(WC_Product $product, bool $show_sale, bool $show_rating, bool $show_cart): void
{
    $image_id    = $product->get_image_id();
    $gallery_ids = $product->get_gallery_image_ids();
    $all_images  = array_filter(array_merge(
        [$image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : ''],
        array_map(fn($id) => wp_get_attachment_image_url($id, 'woocommerce_thumbnail'), $gallery_ids)
    ));
    $categories  = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    $cat_name    = !empty($categories) ? $categories[0] : '';

    $on_sale   = $product->is_on_sale();
    $regular   = (float) $product->get_regular_price();
    $sale      = (float) $product->get_sale_price();
    $sale_pct  = ($on_sale && $regular > 0 && $sale > 0) ? round((($regular - $sale) / $regular) * 100) : 0;
    ?>

    <div class="ps__card" data-images="<?php echo esc_attr(wp_json_encode(array_values($all_images))); ?>">
        <div class="ps__card-image">
            <a href="<?php echo esc_url($product->get_permalink()); ?>">
                <img src="<?php echo esc_url($all_images[0] ?? wc_placeholder_img_src()); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" class="ps__card-img" loading="lazy" />
            </a>

            <?php if ($show_sale && $on_sale) : ?>
                <span class="ps__badge ps__badge--sale">
                    <?php echo $sale_pct > 0 ? $sale_pct . '% OFF' : 'Sale'; ?>
                </span>
            <?php endif; ?>

            <?php if ($product->get_stock_status() === 'outofstock') : ?>
                <span class="ps__badge ps__badge--oos">Sold Out</span>
            <?php endif; ?>

            <button class="ps__wish" aria-label="Add to wishlist">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>

            <?php if (count($all_images) > 1) : ?>
                <div class="ps__thumbs">
                    <?php foreach (array_slice($all_images, 0, 3) as $i => $img) : ?>
                        <button class="ps__thumb <?php echo $i === 0 ? 'is-active' : ''; ?>" data-index="<?php echo $i; ?>">
                            <img src="<?php echo esc_url($img); ?>" alt="" />
                        </button>
                    <?php endforeach; ?>
                    <?php if (count($all_images) > 3) : ?>
                        <a href="<?php echo esc_url($product->get_permalink()); ?>" class="ps__thumb ps__thumb--more">
                            +<?php echo count($all_images) - 3; ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ps__card-body">
            <?php if ($cat_name) : ?>
                <span class="ps__card-cat"><?php echo esc_html($cat_name); ?></span>
            <?php endif; ?>

            <a href="<?php echo esc_url($product->get_permalink()); ?>" class="ps__card-title">
                <?php echo esc_html($product->get_name()); ?>
            </a>

            <?php if ($show_rating && $product->get_average_rating() > 0) : ?>
                <div class="ps__card-rating">
                    <?php echo wc_get_rating_html($product->get_average_rating(), $product->get_rating_count()); ?>
                </div>
            <?php endif; ?>

            <div class="ps__card-price">
                <?php echo $product->get_price_html(); ?>
            </div>

            <?php if ($show_cart && $product->get_stock_status() === 'instock') : ?>
                <div class="ps__card-actions">
                    <a href="<?php echo esc_url($product->add_to_cart_url()); ?>" class="ps__card-btn" data-product-id="<?php echo $product->get_id(); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        ADD TO CART
                    </a>
                    <button class="ps__card-wish-sm" aria-label="Wishlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
}
endif;
