<?php

/**
 * Checkout Form — AI Zippy Override
 *
 * @package AiZippy
 * @version 9.4.0
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

// FORCE ADDRESS IN SESSION/CUSTOMER OBJECT (Crucial for initial render)
if (function_exists('WC') && WC()->customer && $order_mode === 'delivery' && ! empty($session['delivery_address'])) {
	WC()->customer->set_billing_address_1($session['delivery_address']);
	WC()->customer->set_shipping_address_1($session['delivery_address']);
	WC()->customer->set_billing_postcode($session['postal']);
	WC()->customer->set_shipping_postcode($session['postal']);
	WC()->customer->set_billing_country('SG');
	WC()->customer->set_shipping_country('SG');
	WC()->customer->set_billing_city('Singapore');
	WC()->customer->set_shipping_city('Singapore');

	// Recalculate shipping and totals now
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
}

// Inject Autofill into WooCommerce Checkout Fields
add_filter('woocommerce_checkout_get_value', function ($value, $input) use ($session, $order_mode) {
	if ($order_mode === 'delivery' && ! empty($session['delivery_address'])) {
		switch ($input) {
			case 'billing_address_1':
			case 'shipping_address_1':
				return $session['delivery_address'];
			case 'billing_postcode':
			case 'shipping_postcode':
				return $session['postal'];
			case 'billing_country':
			case 'shipping_country':
				return 'SG';
			case 'billing_city':
			case 'shipping_city':
				return 'Singapore';
		}
	}
	return $value;
}, 10, 2);

do_action('woocommerce_before_checkout_form', $checkout);

// Helper for formatting
$formatted_date = ! empty($session['date']) ? date('D, M d, Y', strtotime($session['date'])) : '-';
$formatted_time = '-';
if (! empty($session['time'])) {
	$time_data = is_string($session['time']) ? json_decode($session['time'], true) : $session['time'];
	$formatted_time = (is_array($time_data) && isset($time_data['from'])) ? "From {$time_data['from']} To {$time_data['to']}" : (string)$session['time'];
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout az-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">

	<?php if ($checkout->get_checkout_fields()) : ?>

		<div class="az-checkout__layout">

			<div class="az-checkout__form">
				<div class="az-checkout__card">
					<div class="az-checkout__card-header">
						<h3 class="az-checkout__card-title"><?php esc_html_e('Contact & Billing', 'ai-zippy'); ?></h3>
					</div>
					<div class="az-checkout__card-body">
						<?php do_action('woocommerce_checkout_before_customer_details'); ?>

						<div id="customer_details">
							<div class="woocommerce-billing-fields">
								<?php do_action('woocommerce_checkout_billing'); ?>
							</div>

							<?php if ($order_mode !== 'delivery') : ?>
								<div class="woocommerce-shipping-fields">
									<?php do_action('woocommerce_checkout_shipping'); ?>
								</div>
							<?php endif; ?>
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
					<div class="az-checkout__card-body">

						<!-- SELECTED INFO BOX -->
						<?php if (! empty($order_mode)) : ?>
							<div class="zk__delivery-info" style="margin-bottom: 20px; border: 1px solid #1e293b; padding: 15px; border-radius: 8px; background: #fff;">
								<div style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-bottom: 10px; font-weight: bold; text-transform: uppercase;">
									<span><?php echo esc_html($order_mode === 'takeaway' ? 'Takeaway' : 'Delivery'); ?></span>
									<span style="color: #64748b; font-size: 11px;">SELECTED INFO</span>
								</div>
								<div style="display: grid; gap: 8px; font-size: 13px;">
									<div style="display: grid; grid-template-columns: 80px 1fr;">
										<span style="color: #64748b;">Outlet:</span>
										<span style="font-weight: 600;"><?php echo esc_html($session['outlet_name'] ?? '-'); ?></span>
									</div>
									<?php if ($order_mode === 'delivery') : ?>
										<div style="display: grid; grid-template-columns: 80px 1fr;">
											<span style="color: #64748b;">Address:</span>
											<span style="font-weight: 600;"><?php echo esc_html($session['delivery_address']); ?></span>
										</div>
									<?php endif; ?>
									<div style="display: grid; grid-template-columns: 80px 1fr;">
										<span style="color: #64748b;">Date:</span>
										<span style="font-weight: 600;"><?php echo esc_html($formatted_date); ?></span>
									</div>
									<div style="display: grid; grid-template-columns: 80px 1fr;">
										<span style="color: #64748b;">Time:</span>
										<span style="font-weight: 600;"><?php echo esc_html($formatted_time); ?></span>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<div class="az-checkout__items">
							<?php foreach (WC()->cart->get_cart() as $key => $item) :
								$_product = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);
								if ($_product && $_product->exists() && $item['quantity'] > 0) :
							?>
									<div class="az-checkout__item" data-cart-key="<?php echo esc_attr($key); ?>">
										<div class="az-checkout__item-img"><?php echo $_product->get_image(); ?></div>
										<div class="az-checkout__item-detail">
											<div class="az-checkout__item-top">
												<span class="az-checkout__item-name"><?php echo $_product->get_name(); ?></span>
												<span class="az-checkout__item-total"><?php echo WC()->cart->get_product_subtotal($_product, $item['quantity']); ?></span>
											</div>
											<div class="az-checkout__item-bottom">
												<span class="az-checkout__item-meta">Qty: <?php echo $item['quantity']; ?></span>
												<div class="az-checkout__item-qty">
													<button type="button" class="az-checkout__qty-btn az-checkout__qty-btn--minus">-</button>
													<span class="az-checkout__qty-val"><?php echo $item['quantity']; ?></span>
													<button type="button" class="az-checkout__qty-btn az-checkout__qty-btn--plus">+</button>
												</div>
											</div>
										</div>
									</div>
							<?php endif;
							endforeach; ?>
						</div>

						<div class="az-checkout__totals">
							<div class="az-checkout__totals-row">
								<span>Subtotal</span>
								<span><?php wc_cart_totals_subtotal_html(); ?></span>
							</div>

							<!-- SHIPPING SELECTION -->
							<div class="az-checkout__totals-row az-checkout__shipping-selection">
								<div style="width: 100%;">
									<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
										<span>Shipping</span>
										<span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
									</div>
									<div class="az-checkout__shipping-methods">
										<?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
											<?php wc_cart_totals_shipping_html(); ?>
										<?php endif; ?>
									</div>
								</div>
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
		// Autofill & Lock Address fields if Delivery
		var orderMode = "<?php echo esc_js($order_mode); ?>";
		if (orderMode === 'delivery') {
			var addr = "<?php echo esc_js($session['delivery_address']); ?>";
			var zip = "<?php echo esc_js($session['postal']); ?>";
			var city = "Singapore";

			if (addr) {
				$('#billing_address_1, #shipping_address_1').val(addr).prop('readonly', true);
				$('#billing_country, #shipping_country').val('SG').trigger('change');
				$('#billing_city, #shipping_city').val(city).prop('readonly', true);
				if (zip) $('#billing_postcode, #shipping_postcode').val(zip).prop('readonly', true);

				// Optional: Trigger update_checkout if anything changed
				// $(document.body).trigger('update_checkout');
			}
		}

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
	});
</script>

<style>
	/* Hide the default order review table */
	#order_review table.shop_table {
		display: none !important;
	}

	/* Style for shipping methods in sidebar */
	.az-checkout__shipping-methods ul#shipping_method {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	.az-checkout__shipping-methods ul#shipping_method li {
		font-size: 12px;
		margin-bottom: 5px;
	}

	.az-checkout__shipping-methods input[type="radio"] {
		margin-right: 8px;
	}
</style>