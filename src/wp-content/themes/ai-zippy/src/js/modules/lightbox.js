/**
 * Lightbox Zippy Form - Modal for product order form
 * Creates a modal that Zippy Addons can mount React component into
 *
 * Behavior:
 * - If session has order_mode (user already selected order mode), add directly to cart
 * - If no session, show lightbox popup for user to select
 */

import { addToCart } from "./cart-api.js";
import { updateMiniCart, showToast, spinnerSvg, checkSvg } from "./cart-ui.js";
import { getGroupedCart } from "../order-mode-info/api.js";

/**
 * Check if cart contains any Party Order items.
 */
async function hasPartyOrderItems() {
	try {
		const groups = await getGroupedCart();
		// Conflict exists if any group DOES NOT have an active order session (meaning it's a party order or regular order)
		return groups.some(group => (!group.session || !group.session.order_mode) && group.items.length > 0);
	} catch (err) {
		return false;
	}
}

/**
 * Clear all items from cart.
 */
async function clearAllCartItems() {
	const res = await fetch("/wp-json/wc/store/v1/cart/items", { method: "DELETE" });
	// Also clear session via our custom API using GET to bypass nonce issues
	await fetch("/wp-json/ai-zippy/v1/order-session/clear", { method: "GET" });
	return res.ok;
}

// Cache session info to avoid repeated API calls
let sessionCache = null;
let sessionPromise = null;

export function initLightbox() {
	// Create modal HTML (hidden by default)
	createModal();
	// Create conflict modal HTML
	createConflictModal();

	// Click handler for lightbox buttons - use capture phase (true) to ensure it runs before other listeners
	document.addEventListener("click", handleLightboxClick, true);

	// Close handler
	document.addEventListener("click", handleCloseLightbox);

	// Custom cart event handler
	document.addEventListener("custom_added_to_cart", handleCustomAddedToCart);
}

/**
 * Get session info from API (cached).
 */
async function getSessionInfo(menuId) {
	// Always append a timestamp to avoid browser caching of the REST response
	const timestamp = new Date().getTime();
	let url = `/wp-json/ai-zippy/v1/order-session?_=${timestamp}`;
	if (menuId) url += `&menu_id=${menuId}`;

	try {
		const res = await fetch(url);
		if (!res.ok) throw new Error("API Error");
		const data = await res.json();
		return data || {};
	} catch (err) {
		console.warn("⚠️ Failed to fetch session info in lightbox:", err);
		return {};
	}
}

function createModal() {
	// Check if already exists
	if (document.getElementById("lightbox-zippy-form-wrapper")) {
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

function createConflictModal() {
	if (document.getElementById("zippy-conflict-modal-wrapper")) return;

	const modalHTML = `
		<div id="zippy-conflict-modal-wrapper" class="zippy-lightbox-overlay" style="display: none; z-index: 10001; opacity: 0; transition: opacity 0.3s ease;">
			<div class="zippy-lightbox-modal" style="max-width: 420px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
				<div class="zippy-lightbox-content" style="padding: 32px; text-align: center; background: #fff;">
					<div style="width: 60px; height: 60px; background: #fff5ed; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
						<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#df6f22" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
					</div>
					<h3 style="margin: 0 0 12px; color: #1a1a1a; font-size: 22px; font-weight: 700; font-family: inherit;">Cart Conflict</h3>
					<p style="margin: 0 0 28px; color: #666; font-size: 15px; line-height: 1.6; font-family: inherit;">
						Starting a <strong>Pre-order</strong> will clear your current <strong>Party Order</strong> items. Do you want to continue?
					</p>
					<div style="display: flex; gap: 12px;">
						<button class="zippy-button btn-cancel-conflict" style="flex: 1; height: 48px; border-radius: 12px; background: #f5f5f5; color: #444; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s;">Cancel</button>
						<button class="zippy-button btn-confirm-conflict" style="flex: 1; height: 48px; border-radius: 12px; background: #df6f22; color: #fff; border: none; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(223, 111, 34, 0.3);">Clear & Continue</button>
					</div>
				</div>
			</div>
		</div>
	`;
	document.body.insertAdjacentHTML("beforeend", modalHTML);
}

let pendingOrderData = null;

async function handleLightboxClick(e) {
	const btn = e.target.closest(".lightbox-zippy-btn");
	if (!btn) return;

	// Immediate log to see if click is captured
	console.log("🖱️ Lightbox button clicked!", btn.dataset.product_id);
	
	e.preventDefault();

	const productId = btn.dataset.product_id;
	const menuId = btn.dataset.menuId;
	if (!productId) return;

	// Prevent double-click
	if (btn.classList.contains("is-loading")) return;

	// CHECK CONFLICT: If adding pre-order, check for party order items
	console.log("🔍 Checking for conflicts...");
	const hasConflict = await hasPartyOrderItems();
	console.log("⚠️ Conflict detected:", hasConflict);

	if (hasConflict) {
		pendingOrderData = { productId, menuId, btn };
		const conflictModal = document.getElementById("zippy-conflict-modal-wrapper");
		if (conflictModal) {
			console.log("显示 Conflict Modal");
			conflictModal.style.display = "flex";
			// Force reflow
			conflictModal.offsetHeight;
			conflictModal.style.opacity = "1";
			document.body.classList.add("zippy-lightbox-open");
		}
		return;
	}

	proceedWithOrder(productId, menuId, btn);
}

async function proceedWithOrder(productId, menuId, btn) {
	// Check session for order_mode and specific menuId
	try {
		const session = await getSessionInfo(menuId);

		if (session.order_mode) {
			// User already selected order mode for this menu — add directly to cart
			btn.classList.add("is-loading");
			const originalText = btn.innerHTML;
			btn.innerHTML = spinnerSvg() + " Adding...";

			try {
				const cart = await addToCart(Number(productId), 1, menuId);

				// Success state
				btn.classList.remove("is-loading");
				btn.classList.add("is-added");
				btn.innerHTML = checkSvg();

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
			// No session for this menu — show lightbox popup
			openLightbox(productId, menuId);
		}
	} catch (err) {
		// On error, fallback to showing lightbox
		openLightbox(productId, menuId);
	}
}

function openLightbox(productId, menuId) {
	const form = document.getElementById("lightbox-zippy-form");
	const wrapper = document.getElementById("lightbox-zippy-form-wrapper");

	if (form && wrapper) {
		form.setAttribute("data-product_id", productId);
		if (menuId) {
			form.setAttribute("data-menu-id", menuId);
		}
		wrapper.style.display = "flex";
		// Force reflow
		wrapper.offsetHeight; // eslint-disable-line no-unused-expressions
		wrapper.style.opacity = "1";
		document.body.classList.add("zippy-lightbox-open");
	}
}

function handleCloseLightbox(e) {
	// Close button clicked
	if (e.target.closest(".btn-close-lightbox") || e.target.closest(".btn-cancel-conflict")) {
		e.preventDefault();
		closeModal();
		return;
	}

	// Conflict modal confirm
	if (e.target.closest(".btn-confirm-conflict")) {
		e.preventDefault();
		const confirmBtn = e.target.closest(".btn-confirm-conflict");
		confirmBtn.disabled = true;
		confirmBtn.textContent = "Clearing...";

		clearAllCartItems().then(() => {
			closeModal();
			if (pendingOrderData) {
				proceedWithOrder(pendingOrderData.productId, pendingOrderData.menuId, pendingOrderData.btn);
				pendingOrderData = null;
			}
		}).catch(err => {
			showToast("Failed to clear cart: " + err.message, "error");
			confirmBtn.disabled = false;
			confirmBtn.textContent = "Clear & Continue";
		});
		return;
	}

	// Click outside modal
	if (e.target.classList.contains("zippy-lightbox-overlay")) {
		closeModal();
	}
}

function closeModal() {
	const wrapper = document.getElementById("lightbox-zippy-form-wrapper");
	const conflictWrapper = document.getElementById("zippy-conflict-modal-wrapper");
	const form = document.getElementById("lightbox-zippy-form");

	if (wrapper) {
		wrapper.style.opacity = "0";
		setTimeout(() => {
			wrapper.style.display = "none";
		}, 300);
	}

	if (conflictWrapper) {
		conflictWrapper.style.display = "none";
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
