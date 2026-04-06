/**
 * AJAX Add to Cart — intercepts all add-to-cart links on the page.
 *
 * Works with:
 * - Shop filter React app (.sf__card-btn)
 * - Product Showcase block (.ps__card-btn)
 * - Any link with data-product-id or ?add-to-cart= URL
 */

import { addToCart } from "./cart-api.js";

export function initAddToCart() {
	document.addEventListener("click", handleClick);
}

async function handleClick(e) {
	const btn = e.target.closest(
		'[data-product-id], a[href*="add-to-cart"]',
	);
	if (!btn) return;

	e.preventDefault();

	// Get product ID
	let productId = btn.dataset.productId;
	if (!productId && btn.href) {
		const url = new URL(btn.href, window.location.origin);
		productId = url.searchParams.get("add-to-cart");
	}
	if (!productId) return;

	// Prevent double-click
	if (btn.classList.contains("is-loading")) return;

	const originalText = btn.innerHTML;
	btn.classList.add("is-loading");
	btn.innerHTML = spinnerSvg() + " Adding...";

	try {
		const cart = await addToCart(Number(productId));

		// Success state
		btn.classList.remove("is-loading");
		btn.classList.add("is-added");
		btn.innerHTML = checkSvg() + " Added!";

		// Update mini cart
		updateMiniCart(cart);

		// Show toast
		showToast("Product added to cart", "success");

		// Reset button after 2s
		setTimeout(() => {
			btn.classList.remove("is-added");
			btn.innerHTML = originalText;
		}, 2000);
	} catch (err) {
		btn.classList.remove("is-loading");
		btn.innerHTML = originalText;
		showToast(err.message || "Failed to add to cart", "error");
	}
}

/**
 * Update the WooCommerce mini cart after adding an item.
 *
 * Dispatches a custom event that WC's mini cart block listens to,
 * and manually updates the badge count as a fast visual fallback.
 */
function updateMiniCart(cart) {
	const count = cart.items_count;
	const totalPrice = cart.totals?.total_price;
	const currencySymbol = cart.totals?.currency_symbol || "$";

	// 1. Update badge count immediately
	document.querySelectorAll(".wc-block-mini-cart__badge").forEach((badge) => {
		badge.textContent = count;
		badge.hidden = false;
		badge.setAttribute("aria-hidden", "false");
	});

	// 2. Update amount display
	if (totalPrice) {
		const amount = (parseInt(totalPrice, 10) / 100).toFixed(2);
		document.querySelectorAll(".wc-block-mini-cart__amount").forEach((el) => {
			el.textContent = `${currencySymbol}${amount}`;
		});
	}

	// 3. Update the button's aria-label
	document.querySelectorAll(".wc-block-mini-cart__button").forEach((btn) => {
		btn.setAttribute("aria-label", `${count} items in cart`);
	});

	// 4. Trigger WC Store API invalidation (if wp.data available)
	if (typeof wp !== "undefined" && wp.data) {
		try {
			const store = wp.data.dispatch("wc/store/cart");
			if (store?.invalidateResolutionForStoreCart) {
				store.invalidateResolutionForStoreCart();
			}
		} catch { /* wp.data not ready */ }
	}

	// 5. Dispatch WC blocks event for full refresh
	document.body.dispatchEvent(new Event("wc-blocks_added_to_cart"));

	// 6. Dispatch jQuery event (some WC themes/plugins listen for this)
	if (typeof jQuery !== "undefined") {
		jQuery(document.body).trigger("added_to_cart");
	}
}

/**
 * Show a toast notification.
 */
function showToast(message, type = "success") {
	// Remove existing toasts
	document
		.querySelectorAll(".az-toast")
		.forEach((t) => t.remove());

	const toast = document.createElement("div");
	toast.className = `az-toast az-toast--${type}`;
	toast.innerHTML = `
		<span>${message}</span>
		<button class="az-toast__close" aria-label="Close">&times;</button>
	`;

	document.body.appendChild(toast);

	// Close on click
	toast.querySelector(".az-toast__close").addEventListener("click", () => {
		toast.classList.add("is-closing");
		setTimeout(() => toast.remove(), 300);
	});

	// Auto-dismiss
	setTimeout(() => {
		if (toast.parentNode) {
			toast.classList.add("is-closing");
			setTimeout(() => toast.remove(), 300);
		}
	}, 4000);
}

function spinnerSvg() {
	return '<svg class="az-spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>';
}

function checkSvg() {
	return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>';
}
