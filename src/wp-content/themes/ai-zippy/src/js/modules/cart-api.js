/**
 * WooCommerce Store API — Client-side cart operations.
 *
 * Uses the WC Store API (no nonce needed for public endpoints).
 * Docs: https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/docs/
 */

const STORE_API = "/wp-json/wc/store/v1";

function getNonce() {
	return window.wcBlocksMiddlewareConfig?.storeApiNonce || "";
}

function headers() {
	return {
		"Content-Type": "application/json",
		Nonce: getNonce(),
	};
}

/**
 * Add a product to the cart.
 */
export async function addToCart(productId, quantity = 1) {
	const res = await fetch(`${STORE_API}/cart/add-item`, {
		method: "POST",
		headers: headers(),
		body: JSON.stringify({ id: productId, quantity }),
	});

	if (!res.ok) {
		const err = await res.json().catch(() => ({}));
		throw new Error(err.message || "Failed to add to cart");
	}

	return res.json();
}

/**
 * Update item quantity in cart.
 */
export async function updateCartItem(itemKey, quantity) {
	const res = await fetch(`${STORE_API}/cart/update-item`, {
		method: "POST",
		headers: headers(),
		body: JSON.stringify({ key: itemKey, quantity }),
	});

	if (!res.ok) throw new Error("Failed to update item");
	return res.json();
}

/**
 * Remove an item from the cart.
 */
export async function removeCartItem(itemKey) {
	const res = await fetch(`${STORE_API}/cart/remove-item`, {
		method: "POST",
		headers: headers(),
		body: JSON.stringify({ key: itemKey }),
	});

	if (!res.ok) throw new Error("Failed to remove item");
	return res.json();
}

/**
 * Get current cart.
 */
export async function getCart() {
	const res = await fetch(`${STORE_API}/cart`, { headers: headers() });
	if (!res.ok) throw new Error("Failed to fetch cart");
	return res.json();
}
