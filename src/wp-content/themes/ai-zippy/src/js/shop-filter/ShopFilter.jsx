import { useState, useEffect, useCallback } from "react";
import { fetchProducts, fetchFilterOptions } from "./api.js";
import useFilters from "./useFilters.js";
import FilterPanel from "./components/FilterPanel.jsx";
import ProductGrid from "./components/ProductGrid.jsx";
import Toolbar from "./components/Toolbar.jsx";
import ActiveFilters from "./components/ActiveFilters.jsx";
import Pagination from "./components/Pagination.jsx";
import "./shop-filter.scss";

export default function ShopFilter({ config }) {
	const [layout, setLayout] = useState(config.layout || "sidebar"); // "sidebar" | "top"
	const [viewMode, setViewMode] = useState(
		() => localStorage.getItem("shop-view") || "grid",
	);
	const [options, setOptions] = useState(null);
	const [products, setProducts] = useState([]);
	const [total, setTotal] = useState(0);
	const [pages, setPages] = useState(0);
	const [loading, setLoading] = useState(true);
	const [mobileFilterOpen, setMobileFilterOpen] = useState(false);

	const {
		filters,
		updateFilter,
		resetFilters,
		setSearch,
		toggleAttribute,
		toggleCategory,
	} = useFilters({ per_page: config.per_page || 12 });

	// Load filter options once
	useEffect(() => {
		fetchFilterOptions().then(setOptions).catch(console.error);
	}, []);

	// Load products when filters change
	useEffect(() => {
		setLoading(true);
		fetchProducts(filters)
			.then((data) => {
				setProducts(data.products);
				setTotal(data.total);
				setPages(data.pages);
			})
			.catch(console.error)
			.finally(() => setLoading(false));
	}, [filters]);

	const handleViewChange = useCallback((mode) => {
		setViewMode(mode);
		localStorage.setItem("shop-view", mode);
	}, []);

	const hasActiveFilters =
		filters.search ||
		filters.category ||
		filters.min_price > 0 ||
		filters.max_price > 0 ||
		filters.attributes ||
		filters.stock_status;

	if (!options) {
		return (
			<div className="sf sf--loading">
				<div className="sf__toolbar-skeleton" />
				<div className="sf__body">
					<div className="sf__filters-skeleton" />
					<div className="sf__products">
						<div className={`sf__grid-loading sf__grid-loading--grid`}>
							{Array.from({ length: config.per_page || 12 }).map((_, i) => (
								<div key={i} className="sf__skeleton" />
							))}
						</div>
					</div>
				</div>
			</div>
		);
	}

	return (
		<div className={`sf sf--${layout}`}>
			{/* Toolbar: sort, view toggle, layout switch, mobile filter button */}
			<Toolbar
				layout={layout}
				onLayoutChange={setLayout}
				viewMode={viewMode}
				onViewChange={handleViewChange}
				orderby={filters.orderby}
				order={filters.order}
				onSortChange={(orderby, order) => {
					updateFilter("orderby", orderby);
					setTimeout(() => updateFilter("order", order), 0);
				}}
				total={total}
				onMobileFilterToggle={() => setMobileFilterOpen(!mobileFilterOpen)}
			/>

			{/* Active filters */}
			{hasActiveFilters && (
				<ActiveFilters
					filters={filters}
					options={options}
					onRemoveCategory={toggleCategory}
					onRemoveAttribute={toggleAttribute}
					onClearAll={resetFilters}
				/>
			)}

			<div className="sf__body">
				{/* Filter panel */}
				<FilterPanel
					options={options}
					filters={filters}
					onSearch={setSearch}
					onToggleCategory={toggleCategory}
					onToggleAttribute={toggleAttribute}
					onPriceChange={(min, max) => {
						updateFilter("min_price", min);
						setTimeout(() => updateFilter("max_price", max), 0);
					}}
					onStockChange={(val) => updateFilter("stock_status", val)}
					layout={layout}
					mobileOpen={mobileFilterOpen}
					onMobileClose={() => setMobileFilterOpen(false)}
				/>

				{/* Product grid */}
				<div className="sf__products">
					<ProductGrid
						products={products}
						loading={loading}
						viewMode={viewMode}
						perPage={filters.per_page}
					/>

					{pages > 1 && (
						<Pagination
							current={filters.page}
							total={pages}
							onChange={(p) => updateFilter("page", p)}
						/>
					)}
				</div>
			</div>
		</div>
	);
}
