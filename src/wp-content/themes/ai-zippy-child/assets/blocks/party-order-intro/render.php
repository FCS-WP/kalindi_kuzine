<?php

/**
 * Render for [Party Order] Intro block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

$title         = $attributes['title'] ?? '';
$text_content  = $attributes['content'] ?? '';
$top_img_url   = $attributes['topImageUrl'] ?? '';
$side_img1_url = $attributes['sideImage1Url'] ?? '';
$side_img2_url = $attributes['sideImage2Url'] ?? '';

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'party-order-intro']);
?>

<div <?php echo $wrapper_attributes; ?>>
    <?php if ($top_img_url) : ?>
        <div class="party-order-intro__top-image-wrap">
            <img src="<?php echo esc_url($top_img_url); ?>" alt="" class="party-order-intro__hero-img">
        </div>
    <?php endif; ?>

    <div class="party-order-intro__main-content">
        <div class="party-order-intro__columns">
            <div class="party-order-intro__text-col">
                <?php if ($title) : ?>
                    <h2 class="party-order-intro__title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>

                <?php if ($text_content) : ?>
                    <div class="party-order-intro__content">
                        <?php echo wp_kses_post($text_content); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="party-order-intro__photos-col">
                <?php if ($side_img1_url) : ?>
                    <div class="party-order-intro__side-img-wrap img-1">
                        <img src="<?php echo esc_url($side_img1_url); ?>" alt="">
                    </div>
                <?php endif; ?>

                <?php if ($side_img2_url) : ?>
                    <div class="party-order-intro__side-img-wrap img-2">
                        <img src="<?php echo esc_url($side_img2_url); ?>" alt="">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>