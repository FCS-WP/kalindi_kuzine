import { useState, useCallback, useRef, useEffect } from "react";

const DEFAULTS = {
	search: "",
	category: "",
	min_price: 0,
	max_price: 0,
	attributes: "",
	stock_status: "",
	orderby: "menu_order",
	order: "ASC",
	page: 1,
	per_page: 12,
};

export default function useFilters(config = {}) {
	const [filters, setFilters] = useState(() => {
		// Read initial state from URL params, with config overrides
		const params = new URLSearchParams(window.location.search);
		const initial = { ...DEFAULTS, ...config };

		for (const key of Object.keys(DEFAULTS)) {
			const val = params.get(key);
			if (val !== null) {
				initial[key] = typeof DEFAULTS[key] === "number" ? Number(val) : val;
			}
		}

		return initial;
	});

	const timeoutRef = useRef(null);

	const updateFilter = useCallback((key, value) => {
		setFilters((prev) => ({
			...prev,
			[key]: value,
			page: key === "page" ? value : 1, // Reset page when filter changes
		}));
	}, []);

	const updateMultiple = useCallback((updates) => {
		setFilters((prev) => ({
			...prev,
			...updates,
			page: updates.page ?? 1,
		}));
	}, []);

	const resetFilters = useCallback(() => {
		setFilters({ ...DEFAULTS });
	}, []);

	const setSearch = useCallback(
		(value) => {
			// Debounce search
			clearTimeout(timeoutRef.current);
			timeoutRef.current = setTimeout(() => {
				updateFilter("search", value);
			}, 300);
		},
		[updateFilter],
	);

	// Sync filters to URL
	useEffect(() => {
		const params = new URLSearchParams();
		for (const [key, value] of Object.entries(filters)) {
			if (value !== DEFAULTS[key] && value !== "" && value !== 0) {
				params.set(key, value);
			}
		}
		const qs = params.toString();
		const url = window.location.pathname + (qs ? `?${qs}` : "");
		window.history.replaceState(null, "", url);
	}, [filters]);

	// Build attributes string from object: { pa_color: ['red'], pa_size: ['l'] } -> "pa_color:red|pa_size:l"
	const toggleAttribute = useCallback(
		(taxonomy, termSlug) => {
			setFilters((prev) => {
				const current = prev.attributes ? parseAttributes(prev.attributes) : {};

				if (!current[taxonomy]) {
					current[taxonomy] = [];
				}

				const idx = current[taxonomy].indexOf(termSlug);
				if (idx === -1) {
					current[taxonomy].push(termSlug);
				} else {
					current[taxonomy].splice(idx, 1);
				}

				if (current[taxonomy].length === 0) {
					delete current[taxonomy];
				}

				return {
					...prev,
					attributes: serializeAttributes(current),
					page: 1,
				};
			});
		},
		[],
	);

	const toggleCategory = useCallback(
		(slug) => {
			setFilters((prev) => {
				const current = prev.category ? prev.category.split(",") : [];
				const idx = current.indexOf(slug);

				if (idx === -1) {
					current.push(slug);
				} else {
					current.splice(idx, 1);
				}

				return { ...prev, category: current.join(","), page: 1 };
			});
		},
		[],
	);

	return {
		filters,
		updateFilter,
		updateMultiple,
		resetFilters,
		setSearch,
		toggleAttribute,
		toggleCategory,
	};
}

function parseAttributes(str) {
	const result = {};
	if (!str) return result;

	str.split("|").forEach((group) => {
		const [taxonomy, terms] = group.split(":");
		if (taxonomy && terms) {
			result[taxonomy] = terms.split(",");
		}
	});
	return result;
}

function serializeAttributes(obj) {
	return Object.entries(obj)
		.filter(([, terms]) => terms.length > 0)
		.map(([taxonomy, terms]) => `${taxonomy}:${terms.join(",")}`)
		.join("|");
}
