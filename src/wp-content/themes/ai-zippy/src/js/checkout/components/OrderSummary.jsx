export default function OrderSummary({
	cart,
	session,
	customerAddress,
	couponCode,
	couponError,
	onCouponChange,
	onApplyCoupon,
	onRemoveCoupon,
	onUpdateQty,
	onRemoveItem,
	busyKeys,
	placeOrderButton,
}) {
	const { items, totals, coupons, extensions } = cart;
	const currency = totals.currency_code || "USD";
	const distance = extensions?.zippy_booking?.distance_km || 0;

	const hasSession = session && session.order_mode;

	const formatDate = (dateStr) => {
		if (!dateStr) return "";
		const d = new Date(dateStr);
		return d.toLocaleDateString("en-US", { weekday: "short", day: "numeric", month: "short", year: "numeric" });
	};

	const formatAddress = (addr) => {
		if (!addr || !addr.address_1) return "Not entered yet";
		return [addr.address_1, addr.address_2, addr.city, addr.postcode].filter(Boolean).join(", ");
	};

	const formatTime = (time) => {
		if (!time) return "N/A";
		if (typeof time === "object" && time.from && time.to) {
			return `From ${time.from} To ${time.to}`;
		}
		return String(time);
	};

	return (
		<aside className="zk__sidebar">
			<h3 className="zk__sidebar-title">Order summary</h3>

			{hasSession && (
				<div className="zk__delivery-info">
					<div className="zk__delivery-header">
						<span>{session.order_mode?.toUpperCase()}</span>
						<span className="zk__delivery-mode">SHIPPING</span>
					</div>
					<div className="zk__delivery-grid">
						<div className="zk__delivery-row">
							<span className="zk__delivery-label">Outlet Name:</span>
							<span className="zk__delivery-value">{session.outlet_name || "N/A"}</span>
						</div>
						<div className="zk__delivery-row">
							<span className="zk__delivery-label">Delivery Address:</span>
							<span className="zk__delivery-value">{formatAddress(customerAddress)}</span>
						</div>
						<div className="zk__delivery-row">
							<span className="zk__delivery-label">Delivery Date:</span>
							<span className="zk__delivery-value">{formatDate(session.date)}</span>
						</div>
						<div className="zk__delivery-row">
							<span className="zk__delivery-label">Delivery Time:</span>
							<span className="zk__delivery-value">{formatTime(session.time)}</span>
						</div>
						{session.order_mode === "delivery" && (
							<div className="zk__delivery-row">
								<span className="zk__delivery-label">Shipping Fee:</span>
								<span className="zk__delivery-value">
									{formatPrice(totals.total_shipping, currency)} {distance > 0 ? `- ${distance}km` : ""}
								</span>
							</div>
						)}
					</div>
				</div>
			)}

			<div className="zk__order-items">
				{items.map((item) => {
					const busy = busyKeys?.has(item.key);
					return (
						<div
							key={item.key}
							className="zk__order-item"
							style={busy ? { opacity: 0.5, pointerEvents: "none" } : undefined}
						>
							<div className="zk__order-item-img">
								{item.images?.[0] && (
									<img src={item.images[0].src} alt={item.name} />
								)}
							</div>
							<div className="zk__order-item-detail">
								<div className="zk__order-item-top">
									<span className="zk__order-item-name">{item.name}</span>
									<span className="zk__order-item-total">
										{formatPrice(item.totals?.line_total, currency)}
									</span>
								</div>
								<div className="zk__order-item-bottom">
									<span className="zk__order-item-meta">
										Quantity : {item.quantity}
									</span>
									<div className="zk__order-item-qty">
										<button
											className="zk__qty-btn"
											onClick={() =>
												item.quantity > 1
													? onUpdateQty(item.key, item.quantity - 1)
													: onRemoveItem(item.key)
											}
											aria-label="Decrease"
										>
											−
										</button>
										<span className="zk__qty-val">{item.quantity}</span>
										<button
											className="zk__qty-btn"
											onClick={() => onUpdateQty(item.key, item.quantity + 1)}
											aria-label="Increase"
										>
											+
										</button>
									</div>
								</div>
							</div>
						</div>
					);
				})}
			</div>

			{/* Coupon */}
			<div className="zk__coupon">
				<div className="zk__coupon-row">
					<input
						type="text"
						className="zk__input zk__input--sm"
						value={couponCode}
						onChange={(e) => onCouponChange(e.target.value)}
						placeholder="Coupon code"
						onKeyDown={(e) => e.key === "Enter" && onApplyCoupon()}
					/>
					<button
						className="zk__btn zk__btn--outline zk__btn--sm"
						onClick={onApplyCoupon}
						disabled={!couponCode.trim()}
					>
						Apply
					</button>
				</div>
				{couponError && <span className="zk__field-error">{couponError}</span>}

				{coupons?.length > 0 && (
					<div className="zk__coupon-tags">
						{coupons.map((c) => (
							<span key={c.code} className="zk__coupon-tag">
								{c.code}
								<button
									className="zk__coupon-remove"
									onClick={() => onRemoveCoupon(c.code)}
									aria-label={`Remove coupon ${c.code}`}
								>
									&times;
								</button>
							</span>
						))}
					</div>
				)}
			</div>

			{/* Totals */}
			<div className="zk__totals">
				<div className="zk__totals-row">
					<span>Subtotal</span>
					<span>{formatPrice(totals.total_items, currency)}</span>
				</div>

				{parseInt(totals.total_shipping, 10) > 0 && (
					<div className="zk__totals-row">
						<span>Shipping</span>
						<span>{formatPrice(totals.total_shipping, currency)}</span>
					</div>
				)}

				{parseInt(totals.total_tax, 10) > 0 && (
					<div className="zk__totals-row">
						<span>Tax</span>
						<span>{formatPrice(totals.total_tax, currency)}</span>
					</div>
				)}

				{parseInt(totals.total_discount, 10) > 0 && (
					<div className="zk__totals-row zk__totals-row--discount">
						<span>Discount</span>
						<span>-{formatPrice(totals.total_discount, currency)}</span>
					</div>
				)}

				<div className="zk__totals-row zk__totals-row--total">
					<span>Total</span>
					<span>{formatPrice(totals.total_price, currency)}</span>
				</div>
			</div>

			{placeOrderButton}
		</aside>
	);
}

function formatPrice(priceInCents, currency = "USD") {
	const amount = parseInt(priceInCents || "0", 10) / 100;

	try {
		return new Intl.NumberFormat("en-US", {
			style: "currency",
			currency,
		}).format(amount);
	} catch {
		return `$${amount.toFixed(2)}`;
	}
}
