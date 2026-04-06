import { useState, useRef, useEffect } from "react";

export default function FilterPanel({
	options,
	filters,
	onSearch,
	onToggleCategory,
	onToggleAttribute,
	onPriceChange,
	onStockChange,
	layout,
	mobileOpen,
	onMobileClose,
}) {
	const selectedCategories = filters.category
		? filters.category.split(",")
		: [];

	const selectedAttributes = parseAttributes(filters.attributes);

	const panelClass = [
		"sf__filters",
		layout === "top" ? "sf__filters--top" : "sf__filters--sidebar",
		mobileOpen ? "is-open" : "",
	].join(" ");

	return (
		<>
			{/* Mobile overlay */}
			{mobileOpen && (
				<div className="sf__overlay" onClick={onMobileClose} />
			)}

			<aside className={panelClass}>
				{/* Mobile close button */}
				<div className="sf__filters-header">
					<span className="sf__filters-title">Filters</span>
					<button
						className="sf__filters-close"
						onClick={onMobileClose}
						type="button"
					>
						&times;
					</button>
				</div>

				{/* Search — always visible, no accordion */}
				<div className="sf__section sf__section--search">
					<SearchInput
						defaultValue={filters.search}
						onChange={onSearch}
					/>
				</div>

				{/* Categories */}
				{options.categories.length > 0 && (
					<FilterSection title="Categories" defaultOpen>
						<CategoryList
							categories={options.categories}
							selected={selectedCategories}
							onToggle={onToggleCategory}
						/>
					</FilterSection>
				)}

				{/* Price Range */}
				<FilterSection title="Price" defaultOpen>
					<PriceRange
						min={options.price_range.min}
						max={options.price_range.max}
						currentMin={filters.min_price}
						currentMax={filters.max_price}
						onChange={onPriceChange}
					/>
				</FilterSection>

				{/* Product Attributes (color, size, brand, etc.) */}
				{options.attributes.map((attr) => (
					<FilterSection key={attr.slug} title={attr.name}>
						<AttributeFilter
							attribute={attr}
							selected={selectedAttributes[attr.slug] || []}
							onToggle={(termSlug) =>
								onToggleAttribute(attr.slug, termSlug)
							}
						/>
					</FilterSection>
				))}

				{/* Stock Status */}
				<FilterSection title="Availability">
					<StockFilter
						current={filters.stock_status}
						onChange={onStockChange}
					/>
				</FilterSection>
			</aside>
		</>
	);
}

// --- Sub-components ---

function FilterSection({ title, defaultOpen = false, children }) {
	const [open, setOpen] = useState(defaultOpen);

	return (
		<div className={`sf__section ${open ? "is-open" : ""}`}>
			<button
				className="sf__section-toggle"
				onClick={() => setOpen(!open)}
				type="button"
			>
				<span>{title}</span>
				<svg
					width="12"
					height="12"
					viewBox="0 0 12 12"
					fill="none"
					stroke="currentColor"
					strokeWidth="2"
					style={{
						transform: open ? "rotate(180deg)" : "rotate(0deg)",
						transition: "transform 0.2s",
					}}
				>
					<polyline points="2 4 6 8 10 4" />
				</svg>
			</button>
			{open && <div className="sf__section-content">{children}</div>}
		</div>
	);
}

function SearchInput({ defaultValue, onChange }) {
	const [value, setValue] = useState(defaultValue);

	return (
		<div className="sf__search">
			<svg
				className="sf__search-icon"
				width="16"
				height="16"
				viewBox="0 0 24 24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
			>
				<circle cx="11" cy="11" r="8" />
				<line x1="21" y1="21" x2="16.65" y2="16.65" />
			</svg>
			<input
				type="text"
				className="sf__search-input"
				placeholder="Search name or SKU..."
				value={value}
				onChange={(e) => {
					setValue(e.target.value);
					onChange(e.target.value);
				}}
			/>
			{value && (
				<button
					className="sf__search-clear"
					onClick={() => {
						setValue("");
						onChange("");
					}}
					type="button"
				>
					&times;
				</button>
			)}
		</div>
	);
}

function CategoryList({ categories, selected, onToggle }) {
	// Build tree structure
	const roots = categories.filter((c) => c.parent === 0);
	const children = (parentId) =>
		categories.filter((c) => c.parent === parentId);

	const renderItem = (cat) => (
		<li key={cat.slug}>
			<label className="sf__checkbox">
				<input
					type="checkbox"
					checked={selected.includes(cat.slug)}
					onChange={() => onToggle(cat.slug)}
				/>
				<span className="sf__checkbox-label">{cat.name}</span>
				<span className="sf__checkbox-count">{cat.count}</span>
			</label>
			{children(cat.id).length > 0 && (
				<ul className="sf__cat-children">
					{children(cat.id).map(renderItem)}
				</ul>
			)}
		</li>
	);

	return <ul className="sf__cat-list">{roots.map(renderItem)}</ul>;
}

function PriceRange({ min, max, currentMin, currentMax, onChange }) {
	const [localMin, setLocalMin] = useState(currentMin || min);
	const [localMax, setLocalMax] = useState(currentMax || max);
	const timeoutRef = useRef(null);

	useEffect(() => {
		setLocalMin(currentMin || min);
		setLocalMax(currentMax || max);
	}, [currentMin, currentMax, min, max]);

	const handleChange = (newMin, newMax) => {
		setLocalMin(newMin);
		setLocalMax(newMax);
		clearTimeout(timeoutRef.current);
		timeoutRef.current = setTimeout(() => onChange(newMin, newMax), 400);
	};

	return (
		<div className="sf__price">
			<div className="sf__price-inputs">
				<input
					type="number"
					className="sf__price-input"
					value={localMin}
					min={min}
					max={max}
					onChange={(e) =>
						handleChange(Number(e.target.value), localMax)
					}
					placeholder="Min"
				/>
				<span className="sf__price-sep">&ndash;</span>
				<input
					type="number"
					className="sf__price-input"
					value={localMax}
					min={min}
					max={max}
					onChange={(e) =>
						handleChange(localMin, Number(e.target.value))
					}
					placeholder="Max"
				/>
			</div>
			<input
				type="range"
				className="sf__price-slider"
				min={min}
				max={max}
				value={localMax || max}
				onChange={(e) => handleChange(localMin, Number(e.target.value))}
			/>
		</div>
	);
}

function AttributeFilter({ attribute, selected, onToggle }) {
	return (
		<div className="sf__attr">
			{attribute.options.map((opt) => (
				<label key={opt.slug} className="sf__checkbox">
					<input
						type="checkbox"
						checked={selected.includes(opt.slug)}
						onChange={() => onToggle(opt.slug)}
					/>
					<span className="sf__checkbox-label">{opt.name}</span>
					<span className="sf__checkbox-count">{opt.count}</span>
				</label>
			))}
		</div>
	);
}

function StockFilter({ current, onChange }) {
	const options = [
		{ value: "", label: "All" },
		{ value: "instock", label: "In Stock" },
		{ value: "outofstock", label: "Out of Stock" },
		{ value: "onbackorder", label: "On Backorder" },
	];

	return (
		<div className="sf__stock">
			{options.map((opt) => (
				<label key={opt.value} className="sf__radio">
					<input
						type="radio"
						name="stock_status"
						checked={current === opt.value}
						onChange={() => onChange(opt.value)}
					/>
					<span>{opt.label}</span>
				</label>
			))}
		</div>
	);
}

function parseAttributes(str) {
	const result = {};
	if (!str) return result;
	str.split("|").forEach((group) => {
		const [taxonomy, terms] = group.split(":");
		if (taxonomy && terms) result[taxonomy] = terms.split(",");
	});
	return result;
}
