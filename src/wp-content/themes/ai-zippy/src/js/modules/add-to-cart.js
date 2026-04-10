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
  // Use items_quantity (total products) or fallback to items_count (unique products)
  const count = cart.items_quantity !== undefined ? cart.items_quantity : (cart.items_count || 0);
  const totalPrice = cart.totals?.total_price;
  const currencySymbol = cart.totals?.currency_symbol || "$";

  console.log("🛒 Updating cart UI:", { count, totalPrice });

  // 1. Update/Create badge count with extra selectors for compatibility
  const badgeSelectors = [
    ".wc-block-mini-cart__badge",
    ".wc-block-components-cart-badge",
    ".wp-block-woocommerce-mini-cart-contents > span",
    ".az-cart-count"
  ];
  
  let foundBadge = false;
  badgeSelectors.forEach(selector => {
    document.querySelectorAll(selector).forEach(badge => {
      badge.textContent = count;
      badge.hidden = count === 0;
      badge.style.display = count === 0 ? 'none' : 'flex';
      badge.setAttribute("aria-hidden", count === 0 ? "true" : "false");
      foundBadge = true;
    });
  });

  if (!foundBadge && count > 0) {
    // If no badge found anywhere, try to attach to known cart buttons
    document.querySelectorAll(".wc-block-mini-cart__button, .az-header-cart-link").forEach(btn => {
      const newBadge = document.createElement("span");
      newBadge.className = "wc-block-mini-cart__badge az-cart-count";
      newBadge.style.display = "flex";
      newBadge.textContent = count;
      btn.appendChild(newBadge);
    });
  }

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

  // 4. Force refresh WC Blocks data store aggressively
  if (typeof wp !== "undefined" && wp.data) {
    try {
      const cartStore = wp.data.dispatch("wc/store/cart");
      if (cartStore) {
        // Direct data push
        if (cartStore.receiveCart) cartStore.receiveCart(cart);
        
        // Invalidate resolutions
        if (cartStore.invalidateResolution) {
          cartStore.invalidateResolution("getCart");
        }
        
        // Internal WC Blocks method if available
        if (cartStore.invalidateResolutionForStoreCart) {
          cartStore.invalidateResolutionForStoreCart();
        }
      }
    } catch (err) {
      console.warn("⚠️ Failed to update WC Blocks store:", err);
    }
  }

  // 5. Dispatch events (Standard WC Blocks trigger)
  document.body.dispatchEvent(new CustomEvent("wc-blocks_added_to_cart", { 
    bubbles: true, 
    detail: { preserveCartData: false } 
  }));

  // 6. Legacy / jQuery bits (fallback for older setups or plugins)
  if (typeof jQuery !== "undefined") {
    // Some themes re-fetch fragments on these triggers
    jQuery(document.body).trigger("added_to_cart", [cart.fragments, cart.cart_hash]);
    jQuery(document.body).trigger("wc_fragment_refresh");
    jQuery(document.body).trigger("cart_page_refreshed");
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
