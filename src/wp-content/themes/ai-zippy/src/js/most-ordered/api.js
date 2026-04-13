/**
 * Most Ordered Products API
 * Fetches products by order count, categories, and category-filtered products
 */

const BASE = "/wp-json/ai-zippy/v1";

export async function fetchMostOrdered(params = {}) {
	const query = new URLSearchParams();

	if (params.limit) query.append("limit", params.limit);
	if (params.category) query.append("category", params.category);
	if (params.stock_status) query.append("stock_status", params.stock_status);
	if (params.page) query.append("page", params.page);
	if (params.per_page) query.append("per_page", params.per_page);

	try {
		const response = await fetch(`${BASE}/most-ordered?${query.toString()}`);

		if (!response.ok) {
			throw new Error(`API Error: ${response.status}`);
		}

		const data = await response.json();
		return Array.isArray(data) ? data : [];
	} catch (error) {
		console.error("Failed to fetch most ordered products:", error);
		return [];
	}
}

export async function fetchCategories() {
	try {
		const response = await fetch(`${BASE}/categories`);

		if (!response.ok) {
			throw new Error(`API Error: ${response.status}`);
		}

		const data = await response.json();
		return Array.isArray(data) ? data : [];
	} catch (error) {
		console.error("Failed to fetch categories:", error);
		return [];
	}
}

export async function fetchProductsByCategory(categoryId, params = {}) {
	const query = new URLSearchParams();

	if (params.limit) query.append("limit", params.limit);
	if (params.page) query.append("page", params.page);
	if (params.per_page) query.append("per_page", params.per_page);

	try {
		const url = `${BASE}/products/category/${categoryId}`;
		const response = await fetch(query.toString() ? `${url}?${query}` : url);

		if (!response.ok) {
			throw new Error(`API Error: ${response.status}`);
		}

		const data = await response.json();
		return Array.isArray(data) ? data : [];
	} catch (error) {
		console.error(`Failed to fetch category ${categoryId} products:`, error);
		return [];
	}
}
