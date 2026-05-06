/**
 * Order Mode Info API
 * Fetches session info for order mode display
 */

const BASE = "/wp-json/ai-zippy/v1";

export async function getSessionInfo() {
	try {
		const response = await fetch(`${BASE}/order-session`);

		if (!response.ok) {
			throw new Error(`API Error: ${response.status}`);
		}

		const data = await response.json();
		return data || {};
	} catch (error) {
		console.error("Failed to fetch session info:", error);
		return {};
	}
}

export async function clearSession(menuId = null) {
	try {
		const response = await fetch(`${BASE}/order-session/clear`, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": window.orderModeInfo?.restNonce || "",
			},
			body: JSON.stringify({ menu_id: menuId }),
		});

		if (!response.ok) {
			throw new Error(`API Error: ${response.status}`);
		}

		return await response.json();
	} catch (error) {
		console.error("Failed to clear session:", error);
		throw error;
	}
}

export async function getGroupedCart() {
	try {
		const response = await fetch(`${BASE}/order-session/grouped-cart`);

		if (!response.ok) {
			throw new Error(`API Error: ${response.status}`);
		}

		return await response.json();
	} catch (error) {
		console.error("Failed to fetch grouped cart:", error);
		return [];
	}
}