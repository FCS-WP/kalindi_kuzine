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

		// Update mini cart count
		updateMiniCartBadge(cart.items_count);

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
function updateMiniCartBadge(count) {
	// 1. Update badge text immediately for instant feedback
	document.querySelectorAll(".wc-block-mini-cart__badge").forEach((badge) => {
		badge.textContent = count;
		badge.hidden = false;
	});

	// 2. Trigger WC Store API cart data invalidation so the mini cart
	//    React app re-fetches and updates amount, items, etc.
	if (typeof wp !== "undefined" && wp.data) {
		const store = wp.data.dispatch("wc/store/cart");
		if (store?.invalidateResolutionForStoreCart) {
			store.invalidateResolutionForStoreCart();
		}
	}

	// 3. Fallback: dispatch the event WC blocks listen for
	document.body.dispatchEvent(new Event("wc-blocks_added_to_cart"));
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
