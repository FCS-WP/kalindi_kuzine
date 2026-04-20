<?php

/**
 * Render for [Party Order] CTA block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

$text           = $attributes['text'] ?? '';
$menu_label     = $attributes['menuLabel'] ?? '';
$menu_url       = $attributes['menuUrl'] ?? '#';
$whatsapp_label = $attributes['whatsappLabel'] ?? '';
$whatsapp_url   = $attributes['whatsappUrl'] ?? '#';

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'party-order-cta']);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="party-order-cta__container">
        <?php if ($text) : ?>
            <p class="party-order-cta__text">
                <?php echo wp_kses_post($text); ?>
            </p>
        <?php endif; ?>

        <div class="party-order-cta__buttons">
            <?php if ($menu_label) : ?>
                <a href="<?php echo esc_url($menu_url); ?>" class="az-btn az-btn--outline">
                    <?php echo esc_html($menu_label); ?>
                </a>
            <?php endif; ?>

            <?php if ($whatsapp_label) : ?>
                <a href="<?php echo esc_url($whatsapp_url); ?>" class="az-btn az-btn--solid">
                    <?php echo esc_html($whatsapp_label); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>