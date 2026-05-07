<?php

/**
 * Checkout Form — AI Zippy Override
 *
 * @package AiZippy
 * @version 9.4.2
 */

if (! defined('ABSPATH')) {
	exit;
}

// Fetch AI Zippy Session data
$session = [];
if (function_exists('WC')) {
	if (WC()->session === null) {
		WC()->session = new \WC_Session_Handler();
		WC()->session->init();
	}
	$session = [
		'order_mode'       => WC()->session->get('order_mode'),
		'date'             => WC()->session->get('date'),
		'time'             => WC()->session->get('time'),
		'outlet_name'      => WC()->session->get('outlet_name'),
		'outlet_address'   => WC()->session->get('outlet_address'),
		'delivery_address' => WC()->session->get('delivery_address') ?: WC()->session->get('address_name'),
		'postal'           => WC()->session->get('postal'),
		'total_distance'   => (float) WC()->session->get('total_distance'),
		'shipping_fee'     => (float) WC()->session->get('shipping_fee'),
	];
}
$order_mode = $session['order_mode'] ?? '';

// FORCE ADDRESS IN SESSION/CUSTOMER OBJECT (Ensure shipping total calculation)
if (function_exists('WC') && WC()->customer && ! empty($session['delivery_address'])) {
	WC()->customer->set_billing_address_1($session['delivery_address']);
	WC()->customer->set_shipping_address_1($session['delivery_address']);
	WC()->customer->set_billing_postcode($session['postal']);
	WC()->customer->set_shipping_postcode($session['postal']);
	WC()->customer->set_billing_country('SG');
	WC()->customer->set_shipping_country('SG');
	WC()->customer->set_billing_city('Singapore');
	WC()->customer->set_shipping_city('Singapore');

	// Recalculate shipping and totals
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
}

do_action('woocommerce_before_checkout_form', $checkout);
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout az-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">

	<?php if ($checkout->get_checkout_fields()) : ?>

		<div class="az-checkout__layout">

			<div class="az-checkout__form">
				<div class="az-checkout__card">
					<div class="az-checkout__card-header">
						<h3 class="az-checkout__card-title"><?php esc_html_e('Contact Information', 'ai-zippy'); ?></h3>
					</div>
					<div class="az-checkout__card-body">
						<?php do_action('woocommerce_checkout_before_customer_details'); ?>

						<div id="customer_details">
							<div class="woocommerce-billing-fields">
								<?php do_action('woocommerce_checkout_billing'); ?>
							</div>
						</div>

						<?php do_action('woocommerce_checkout_after_customer_details'); ?>
					</div>
				</div>

				<?php if (apply_filters('woocommerce_enable_order_notes_field', 'yes' === get_option('woocommerce_enable_order_comments', 'yes'))) : ?>
					<div class="az-checkout__card az-checkout__card--notes">
						<div class="az-checkout__card-header">
							<h3 class="az-checkout__card-title"><?php esc_html_e('Additional information', 'ai-zippy'); ?></h3>
						</div>
						<div class="az-checkout__card-body">
							<?php do_action('woocommerce_before_order_notes', $checkout); ?>
							<div class="woocommerce-additional-fields">
								<?php foreach ($checkout->get_checkout_fields('order') as $key => $field) : ?>
									<?php woocommerce_form_field($key, $field, $checkout->get_value($key)); ?>
								<?php endforeach; ?>
							</div>
							<?php do_action('woocommerce_after_order_notes', $checkout); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="az-checkout__sidebar">
				<div class="az-checkout__card az-checkout__card--sticky">
					<div class="az-checkout__card-header">
						<h3 class="az-checkout__card-title"><?php esc_html_e('Order summary', 'ai-zippy'); ?></h3>
					</div>
					<div class="az-checkout__card-body" id="az-checkout-sidebar-fragments">

						<div class="az-checkout__items omi-grouped-container">
							<?php
							$grouped_items = [];
							foreach (WC()->cart->get_cart() as $key => $item) {
								$menu_id = $item['menu_id'] ?? 'default';
								if (!isset($grouped_items[$menu_id])) {
									$grouped_items[$menu_id] = [];
								}
								$grouped_items[$menu_id][$key] = $item;
							}

							foreach ($grouped_items as $menu_id => $items) :
								$suffix = ($menu_id && $menu_id !== 'default') ? '_' . $menu_id : '';
								$g_order_mode = WC()->session->get('order_mode' . $suffix);
								$g_outlet_name = WC()->session->get('outlet_name' . $suffix);
								$g_date = WC()->session->get('date' . $suffix);
								$g_time = WC()->session->get('time' . $suffix);
								$g_shipping_fee = (float) WC()->session->get('shipping_fee' . $suffix);

								$g_formatted_date = ! empty($g_date) ? date('D, M d, Y', strtotime($g_date)) : '-';
								$g_formatted_time = '-';
								if (! empty($g_time)) {
									$g_time_data = is_string($g_time) ? json_decode($g_time, true) : $g_time;
									$g_formatted_time = (is_array($g_time_data) && isset($g_time_data['from'])) ? "{$g_time_data['from']} - {$g_time_data['to']}" : (string)$g_time;
								}
							?>
								<div class="omi-group-card">
									<div class="omi-group-card__header">
										<div class="omi-group-card__info">
											<div>
												<span class="omi-label">MODE:</span>
												<span class="omi-value"><?php echo esc_html($g_order_mode === 'takeaway' ? 'Takeaway' : 'Delivery'); ?></span>
											</div>
											<div>
												<span class="omi-label">OUTLET:</span>
												<span class="omi-value"><?php echo esc_html($g_outlet_name ?: '-'); ?></span>
											</div>
											<div>
												<span class="omi-label">TIME:</span>
												<span class="omi-value"><?php echo esc_html($g_formatted_date); ?> (<?php echo esc_html($g_formatted_time); ?>)</span>
											</div>
										</div>
										<button type="button" class="omi-group-card__reset az-checkout__group-reset" data-menu-id="<?php echo esc_attr($menu_id); ?>">RESET</button>
									</div>
									<div class="omi-group-card__items">
										<?php foreach ($items as $key => $item) :
											$_product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);
											if ($_product && $_product->exists() && $item['quantity'] > 0) :
										?>
												<div class="az-checkout__item omi-item" data-cart-key="<?php echo esc_attr($key); ?>">
													<div class="az-checkout__item-img omi-item__image"><?php echo $_product->get_image(); ?></div>
													<div class="az-checkout__item-detail omi-item__content">
														<div class="az-checkout__item-top">
															<span class="az-checkout__item-name omi-item__name"><?php echo $_product->get_name(); ?></span>
														</div>
														<div class="az-checkout__item-bottom omi-item__meta">
															<span class="az-checkout__item-meta">Qty: <?php echo $item['quantity']; ?></span>
															<span class="az-checkout__item-total"><?php echo WC()->cart->get_product_subtotal($_product, $item['quantity']); ?></span>
														</div>
													</div>
												</div>
										<?php endif;
										endforeach; ?>

										<!-- GROUP SHIPPING FEE -->
										<div class="omi-group-shipping">
											<span class="omi-label">SHIPPING:</span>
											<span class="omi-value">
												<?php if ($g_order_mode === 'takeaway') : ?>
													<strong>Free</strong>
												<?php else : ?>
													<strong><?php echo wc_price($g_shipping_fee); ?></strong>
												<?php endif; ?>
											</span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="az-checkout__totals">
							<div class="az-checkout__totals-row">
								<span>Subtotal</span>
								<span><?php wc_cart_totals_subtotal_html(); ?></span>
							</div>

							<!-- TOTAL SHIPPING -->
							<div class="az-checkout__totals-row">
								<span>Shipping Total</span>
								<span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
							</div>

							<?php foreach (WC()->cart->get_fees() as $fee) : ?>
								<div class="az-checkout__totals-row">
									<span><?php echo esc_html($fee->name); ?></span>
									<span><?php wc_cart_totals_fee_html($fee); ?></span>
								</div>
							<?php endforeach; ?>

							<div class="az-checkout__totals-row az-checkout__totals-row--total">
								<span>Total</span>
								<span><?php wc_cart_totals_order_total_html(); ?></span>
							</div>
						</div>

						<div id="order_review" class="woocommerce-checkout-review-order">
							<?php do_action('woocommerce_checkout_order_review'); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>

<script>
	jQuery(function($) {
		$(document).on('click', '.az-checkout__qty-btn', function() {
			var $item = $(this).closest('.az-checkout__item');
			var key = $item.data('cart-key');
			var val = parseInt($item.find('.az-checkout__qty-val').text());
			var newQty = $(this).hasClass('az-checkout__qty-btn--plus') ? val + 1 : val - 1;
			if (newQty < 1) return;

			$item.css('opacity', '0.5');
			$.post('<?php echo admin_url("admin-ajax.php"); ?>', {
				action: 'az_update_checkout_qty',
				cart_key: key,
				quantity: newQty,
				security: '<?php echo wp_create_nonce("az-checkout-qty"); ?>'
			}, function() {
				location.reload();
			});
		});

		// Group Reset Logic
		$(document).on('click', '.az-checkout__group-reset', function() {
			var menuId = $(this).data('menu-id');
			if (!confirm('Are you sure you want to reset this menu group? This will clear the session and remove associated items from your cart.')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('RESETTING...');

			$.ajax({
				url: '/wp-json/ai-zippy/v1/order-session/clear',
				method: 'POST',
				data: { menu_id: menuId },
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Failed to reset: ' + (response.message || 'Unknown error'));
						$btn.prop('disabled', false).text('RESET');
					}
				},
				error: function() {
					alert('Connection error. Please try again.');
					$btn.prop('disabled', false).text('RESET');
				}
			});
		});
	});
</script>

<style>
	/* Hide the default order review table and shipping methods list */
	#order_review table.shop_table,
	.az-checkout__shipping-selection,
	ul#shipping_method,
	.woocommerce-shipping-totals,
	.woocommerce-shipping-fields {
		display: none !important;
	}

	/* OMI Group Card Styling (Matched with Sidebar) */
	.omi-grouped-container {
		display: flex;
		flex-direction: column;
		gap: 20px;
		margin-bottom: 30px;
		width: 100%;
	}

	.omi-group-card {
		background: #fff;
		border: 1px solid rgba(0, 0, 0, 0.05);
		border-radius: 16px;
		overflow: hidden;
		box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
		display: flex;
		flex-direction: column;
		margin-bottom: 20px;
	}

	.omi-group-card__header {
		background: linear-gradient(135deg, #df6f22 0%, #ff8c42 100%);
		padding: 15px 20px;
		color: #fff;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.omi-group-card__info {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.omi-label {
		font-weight: 800;
		font-size: 10px;
		text-transform: uppercase;
		letter-spacing: 0.05em;
		opacity: 0.85;
		margin-right: 8px;
		display: inline-block;
		min-width: 50px;
	}

	.omi-value {
		font-weight: 600;
		font-size: 13px;
	}

	.omi-group-card__reset {
		background: rgba(255, 255, 255, 0.25);
		border: 1px solid rgba(255, 255, 255, 0.4);
		color: #fff;
		padding: 4px 12px;
		border-radius: 6px;
		font-size: 10px;
		font-weight: 800;
		cursor: pointer;
		backdrop-filter: blur(4px);
		transition: all 0.2s;
	}

	.omi-group-card__reset:hover {
		background: #fff;
		color: #df6f22;
	}

	.omi-group-card__items {
		padding: 15px 20px;
	}

	.omi-item {
		display: flex;
		gap: 15px;
		align-items: center;
		padding-bottom: 15px;
		border-bottom: 1px dashed #eee;
		margin-bottom: 15px;
	}

	.omi-item:last-child {
		border-bottom: none;
		padding-bottom: 0;
		margin-bottom: 0;
	}

	.omi-item__image {
		width: 50px;
		height: 50px;
		border-radius: 8px;
		overflow: hidden;
		flex-shrink: 0;
	}

	.omi-item__image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	.omi-item__name {
		font-weight: 700;
		font-size: 14px;
		color: #1a1a1a;
	}

	.omi-item__meta {
		display: flex;
		justify-content: space-between;
		font-size: 12px;
		margin-top: 5px;
	}

	.omi-item__meta span:last-child {
		color: #df6f22;
		font-weight: 800;
	}

	.omi-group-shipping {
		margin-top: 15px;
		padding-top: 15px;
		border-top: 1px solid #f0f0f0;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.omi-group-shipping .omi-label {
		color: #64748b;
	}

	.omi-group-shipping .omi-value {
		color: #1a1a1a;
		font-size: 14px;
	}
</style>