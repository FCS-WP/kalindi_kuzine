/**
 * Lightbox Zippy Form - Modal for product order form
 * Creates a modal that Zippy Addons can mount React component into
 *
 * Behavior:
 * - If session has order_mode (user already selected order mode), add directly to cart
 * - If no session, show lightbox popup for user to select
 */

import { addToCart } from "./cart-api.js";

// Cache session info to avoid repeated API calls
let sessionCache = null;
let sessionPromise = null;

export function initLightbox() {
	// Create modal HTML (hidden by default)
	createModal();

	// Click handler for lightbox buttons
	document.addEventListener("click", handleLightboxClick);

	// Close handler
	document.addEventListener("click", handleCloseLightbox);

	// Custom cart event handler
	document.addEventListener("custom_added_to_cart", handleCustomAddedToCart);
}

/**
 * Get session info from API (cached).
 */
async function getSessionInfo() {
	// Return cached data if available
	if (sessionCache !== null) {
		return sessionCache;
	}

	// Return existing promise if request is in flight
	if (sessionPromise) {
		return sessionPromise;
	}

	sessionPromise = fetch("/wp-json/ai-zippy/v1/order-session")
		.then((res) => res.json())
		.then((data) => {
			sessionCache = data || {};
			sessionPromise = null;
			return sessionCache;
		})
		.catch(() => {
			sessionCache = {};
			sessionPromise = null;
			return sessionCache;
		});

	return sessionPromise;
}

function createModal() {
	// Check if already exists
	if (document.getElementById("lightbox-zippy-form")) {
		return;
	}

	const modalHTML = `
		<div id="lightbox-zippy-form-wrapper" class="zippy-lightbox-overlay" style="display: none;">
			<div class="zippy-lightbox-modal">
				<button class="zippy-lightbox-close btn-close-lightbox" aria-label="Close">&times;</button>
				<div id="lightbox-zippy-form" class="zippy-lightbox-content" data-product_id="">
					<div id="zippy-form"></div>
				</div>
			</div>
		</div>
	`;

	document.body.insertAdjacentHTML("beforeend", modalHTML);
}

async function handleLightboxClick(e) {
	const btn = e.target.closest(".lightbox-zippy-btn");

	if (!btn) return;

	e.preventDefault();

	const productId = btn.dataset.product_id;
	if (!productId) return;

	// Prevent double-click
	if (btn.classList.contains("is-loading")) return;

	// Check session for order_mode
	try {
		const session = await getSessionInfo();

		if (session.order_mode) {
			// User already selected order mode — add directly to cart
			btn.classList.add("is-loading");
			const originalText = btn.innerHTML;
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
		} else {
			// No session — show lightbox popup
			openLightbox(productId);
		}
	} catch (err) {
		// On error, fallback to showing lightbox
		openLightbox(productId);
	}
}

function openLightbox(productId) {
	const form = document.getElementById("lightbox-zippy-form");
	const wrapper = document.getElementById("lightbox-zippy-form-wrapper");

	if (form && wrapper) {
		form.setAttribute("data-product_id", productId);
		wrapper.style.display = "flex";
		// Force reflow
		wrapper.offsetHeight; // eslint-disable-line no-unused-expressions
		wrapper.style.opacity = "1";
		document.body.classList.add("zippy-lightbox-open");
	}
}

function handleCloseLightbox(e) {
	// Close button clicked
	if (e.target.closest(".btn-close-lightbox")) {
		e.preventDefault();
		closeModal();
		return;
	}

	// Click outside modal
	if (e.target.classList.contains("zippy-lightbox-overlay")) {
		closeModal();
	}
}

function closeModal() {
	const wrapper = document.getElementById("lightbox-zippy-form-wrapper");
	const form = document.getElementById("lightbox-zippy-form");

	if (wrapper) {
		wrapper.style.opacity = "0";
		setTimeout(() => {
			wrapper.style.display = "none";
		}, 300);
	}

	if (form) {
		form.removeAttribute("data-product_id");
	}

	document.body.classList.remove("zippy-lightbox-open");
}

function handleCustomAddedToCart() {
	if (typeof Swal !== "undefined") {
		Swal.fire({
			title: "Successfully",
			text: "Product added to cart!",
			icon: "success",
			customClass: {
				confirmButton: "custom-swal-btn-success",
			},
			allowOutsideClick: false,
		}).then((result) => {
			if (result.isConfirmed) {
				window.location.href = "/";
			}
		});
	}
}

/**
 * Update the WooCommerce mini cart after adding an item.
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
	document.querySelectorAll(".az-toast").forEach((t) => t.remove());

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