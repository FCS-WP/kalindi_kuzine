/**
 * Shared UI logic for cart operations.
 */

export function updateMiniCart(cart) {
  if (!cart) return;

  // Use items_quantity (total products) or fallback to items_count (unique products)
  const count =
    cart.items_quantity !== undefined
      ? cart.items_quantity
      : cart.items_count || 0;
  const totalPrice = cart.totals?.total_price;
  const currencySymbol = cart.totals?.currency_symbol || "$";

  // 1. Update/Create badge count with extra selectors for compatibility
  const badgeSelectors = [
    ".wc-block-mini-cart__badge",
    ".wc-block-components-cart-badge",
    ".wp-block-woocommerce-mini-cart-contents > span",
    ".az-cart-count",
  ];

  let foundBadge = false;
  badgeSelectors.forEach((selector) => {
    document.querySelectorAll(selector).forEach((badge) => {
      badge.textContent = count;
      badge.hidden = count === 0;
      badge.style.display = count === 0 ? "none" : "flex";
      badge.setAttribute("aria-hidden", count === 0 ? "true" : "false");
      foundBadge = true;
    });
  });

  if (!foundBadge && count > 0) {
    // If no badge found anywhere, try to attach to known cart buttons
    document
      .querySelectorAll(".wc-block-mini-cart__button, .az-header-cart-link")
      .forEach((btn) => {
        if (btn.querySelector(".az-cart-count")) return;

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
    document
      .querySelectorAll(
        ".wc-block-mini-cart__amount, .wc-block-components-cart-total",
      )
      .forEach((el) => {
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
      if (wp.data.dispatch("wc/store/cart")) {
        const { dispatch } = wp.data;
        dispatch("wc/store/cart").invalidateResolution("getCart");
        if (dispatch("wc/store/cart").receiveCart) {
          dispatch("wc/store/cart").receiveCart(cart);
        }
      }
    } catch (err) {
      console.warn("⚠️ Failed to update WC Blocks store:", err);
    }
  }

  // 5. Dispatch events (Standard WC Blocks trigger)
  document.body.dispatchEvent(
    new CustomEvent("wc-blocks_added_to_cart", {
      bubbles: true,
      detail: { preserveCartData: false },
    }),
  );

  // 6. Legacy / jQuery bits
  if (typeof jQuery !== "undefined") {
    jQuery(document.body).trigger("added_to_cart", [
      cart.fragments || {},
      cart.cart_hash || "",
      null,
    ]);
    jQuery(document.body).trigger("wc_fragment_refresh");
  }
}

export function showToast(message, type = "success") {
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

export function spinnerSvg() {
  return '<svg class="az-spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>';
}

export function checkSvg() {
  return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>';
}
