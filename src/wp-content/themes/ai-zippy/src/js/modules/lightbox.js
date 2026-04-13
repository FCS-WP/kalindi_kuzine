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
		.then((res) => {
			if (!res.ok) throw new Error("API Error");
			return res.json();
		})
		.then((data) => {
			sessionCache = data || {};
			sessionPromise = null;
			return sessionCache;
		})
		.catch((err) => {
			console.warn("⚠️ Failed to fetch session info in lightbox:", err);
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
				btn.innerHTML = checkSvg(); // Removed " Added!" text as per request

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
