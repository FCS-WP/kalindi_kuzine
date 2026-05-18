import { useState, useEffect, useCallback, useRef } from "react";
import { getCart, updateItemQty, removeItem, applyCoupon, removeCoupon, clearCart } from "./api.js";
import CartSteps from "./components/CartSteps.jsx";
import CartItems from "./components/CartItems.jsx";
import CartSidebar from "./components/CartSidebar.jsx";
import CartEmpty from "./components/CartEmpty.jsx";

export default function CartApp({ checkoutUrl, shopUrl }) {
	const [cart, setCart] = useState(null);
	const [loading, setLoading] = useState(true);
	const [busyKeys, setBusyKeys] = useState(new Set());
	const [error, setError] = useState(null);

	const isFetching = useRef(false);

	// Load cart
	const loadData = useCallback(async (isAutoRefresh = false) => {
		if (isFetching.current) return;
		
		isFetching.current = true;
		if (!isAutoRefresh) setLoading(true);

		try {
			const data = await getCart();
			setCart(data);
		} catch (err) {
			console.error("Failed to load cart:", err);
			setError("Failed to load cart");
		} finally {
			setLoading(false);
			// Small timeout to prevent immediate re-triggering from events
			setTimeout(() => {
				isFetching.current = false;
			}, 500);
		}
	}, []);

	useEffect(() => {
		loadData();

		// On the cart page, we don't want to listen to external refreshes
		// because the CartApp itself is the one changing the cart.
		// This prevents infinite loops with WooCommerce's fragment refresh.
		const isCartPage = window.location.pathname.includes("/cart");
		if (isCartPage) {
			console.log("🚫 Cart page detected, disabling external refresh listeners to prevent loops.");
			return;
		}

		// Listen for cart events to refresh data (only on non-cart pages like Shop)
		let refreshTimer;
		const handleRefresh = (e) => {
			if (isFetching.current) return;
			
			clearTimeout(refreshTimer);
			refreshTimer = setTimeout(() => {
				const eventName = e?.type || (e instanceof CustomEvent ? e.type : "unknown");
				console.log(`🔄 Cart updated externally via [${eventName}], refreshing CartApp...`);
				loadData(true);
			}, 300); // Debounce refresh
		};

		document.body.addEventListener("wc-blocks_added_to_cart", handleRefresh);
		
		const $ = window.jQuery;
		if ($) {
			$(document.body).on("added_to_cart removed_from_cart updated_cart_totals", handleRefresh);
		}

		return () => {
			clearTimeout(refreshTimer);
			document.body.removeEventListener("wc-blocks_added_to_cart", handleRefresh);
			if ($) {
				$(document.body).off("added_to_cart removed_from_cart updated_cart_totals", handleRefresh);
			}
		};
	}, [loadData]);

	const markBusy = useCallback((key, busy) => {
		setBusyKeys((prev) => {
			const next = new Set(prev);
			busy ? next.add(key) : next.delete(key);
			return next;
		});
	}, []);

	const handleUpdateQty = useCallback(async (key, qty) => {
		markBusy(key, true);
		try {
			const updated = await updateItemQty(key, qty);
			setCart(updated);
		} catch {
			setError("Failed to update quantity");
		} finally {
			markBusy(key, false);
		}
	}, [markBusy]);

	const handleRemove = useCallback(async (key) => {
		markBusy(key, true);
		try {
			const updated = await removeItem(key);
			setCart(updated);
		} catch {
			setError("Failed to remove item");
		} finally {
			markBusy(key, false);
		}
	}, [markBusy]);

	const handleClearCart = useCallback(async () => {
		if (!cart?.items?.length) return;
		setLoading(true);
		try {
			const updated = await clearCart(cart.items);
			setCart(updated);
		} catch {
			setError("Failed to clear cart");
		} finally {
			setLoading(false);
		}
	}, [cart]);

	const handleApplyCoupon = useCallback(async (code) => {
		try {
			const updated = await applyCoupon(code);
			setCart(updated);
			return null;
		} catch (err) {
			return err.message;
		}
	}, []);

	const handleRemoveCoupon = useCallback(async (code) => {
		try {
			const updated = await removeCoupon(code);
			setCart(updated);
		} catch {
			setError("Failed to remove coupon");
		}
	}, []);

	// Dismiss error
	useEffect(() => {
		if (!error) return;
		const t = setTimeout(() => setError(null), 4000);
		return () => clearTimeout(t);
	}, [error]);

	// Loading skeleton
	if (loading && !cart) {
		return (
			<div className="zc">
				<CartSteps current={1} />
				<div className="zc__skeleton">
					<div className="zc__skeleton-items">
						{[1, 2, 3].map((i) => (
							<div key={i} className="zc__skeleton-row" />
						))}
					</div>
					<div className="zc__skeleton-sidebar" />
				</div>
			</div>
		);
	}

	// Empty cart
	if (!cart?.items?.length) {
		return (
			<div className="zc">
				<CartSteps current={1} />
				<CartEmpty shopUrl={shopUrl} />
			</div>
		);
	}

	const itemCount = cart.items.reduce((sum, item) => sum + item.quantity, 0);

	return (
		<div className="zc">
			{error && <div className="zc__error">{error}</div>}

			<CartSteps current={1} />

			<div className="zc__layout">
				<CartItems
					items={cart.items}
					itemCount={itemCount}
					busyKeys={busyKeys}
					loading={loading}
					onUpdateQty={handleUpdateQty}
					onRemove={handleRemove}
					onClearCart={handleClearCart}
				/>
				<CartSidebar
					totals={cart.totals}
					coupons={cart.coupons}
					checkoutUrl={checkoutUrl}
					onApplyCoupon={handleApplyCoupon}
					onRemoveCoupon={handleRemoveCoupon}
				/>
			</div>
		</div>
	);
}
