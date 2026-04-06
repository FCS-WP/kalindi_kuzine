const BASE = "/wp-json/ai-zippy/v1";

export async function fetchProducts(params = {}) {
	const query = new URLSearchParams();

	Object.entries(params).forEach(([key, value]) => {
		if (value !== "" && value !== null && value !== undefined) {
			query.set(key, value);
		}
	});

	const res = await fetch(`${BASE}/products?${query.toString()}`);
	if (!res.ok) throw new Error("Failed to fetch products");
	return res.json();
}

export async function fetchFilterOptions() {
	const res = await fetch(`${BASE}/filter-options`);
	if (!res.ok) throw new Error("Failed to fetch filter options");
	return res.json();
}
