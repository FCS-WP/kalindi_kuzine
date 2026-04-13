/**
 * AJAX Add to Cart — intercepts all add-to-cart links on the page.
 *
 * Works with:
 * - Shop filter React app (.sf__card-btn)
 * - Product Showcase block (.ps__card-btn)
 * - Any link with data-product-id or ?add-to-cart= URL
 */

import { addToCart, getCart } from "./cart-api.js";
import { updateMiniCart, showToast, spinnerSvg, checkSvg } from "./cart-ui.js";

export function initAddToCart() {
  document.addEventListener("click", handleClick);

  // Initialize mini cart badge on page load
  initMiniCart();
}

async function initMiniCart() {
  try {
    const cart = await getCart();
    updateMiniCart(cart);
  } catch (err) {
    console.warn("🛒 Failed to init mini cart:", err);
  }
}

async function handleClick(e) {
  const btn = e.target.closest('[data-product-id], a[href*="add-to-cart"]');
  if (!btn) return;

  // Skip if this is a lightbox button (handled by lightbox.js)
  if (btn.classList.contains("lightbox-zippy-btn")) {
    return;
  }

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
}


