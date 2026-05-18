import { useEffect, useState, useCallback, useRef } from "react";
import { fetchMostOrdered, fetchCategories, fetchProductsByMenu, fetchSessionInfo, fetchCategoriesByMenu } from "./api";
import "./style.scss";

export default function MostOrdered({ limit: propLimit, menuUrl }) {
	const [categories, setCategories] = useState([]);
	const [products, setProducts] = useState([]);
	const [activeTab, setActiveTab] = useState("");
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [searchQuery, setSearchQuery] = useState("");
	const [page, setPage] = useState(1);
	const [hasMore, setHasMore] = useState(true);
	const [sessionData, setSessionData] = useState({ date: null, order_mode: null });
	const [productCategories, setProductCategories] = useState([]);
	const [activeCategory, setActiveCategory] = useState(null);
	const searchTimeout = useRef(null);
	const PER_PAGE = propLimit || 4;

	// Load categories and session info on mount
	useEffect(() => {
		loadCategories();
		loadSession();
	}, []);

	// Reload products and categories when active tab changes
	useEffect(() => {
		if (activeTab) {
			setPage(1);
			setActiveCategory(null);
			loadProductCategories(activeTab);
			loadProducts(1, true, searchQuery, null);
		}
	}, [activeTab]);

	// Reload products when active category changes
	useEffect(() => {
		setPage(1);
		loadProducts(1, true, searchQuery, activeCategory);
	}, [activeCategory]);

	// Debounced search
	useEffect(() => {
		if (searchTimeout.current) clearTimeout(searchTimeout.current);
		
		searchTimeout.current = setTimeout(() => {
			setPage(1);
			loadProducts(1, true, searchQuery);
		}, 500);

		return () => clearTimeout(searchTimeout.current);
	}, [searchQuery]);

	const loadSession = async () => {
		const data = await fetchSessionInfo();
		setSessionData(data);
	};

	const loadCategories = async () => {
		try {
			const data = await fetchCategories();
			const cats = Array.isArray(data) ? data : Object.values(data || {});
			setCategories(cats);
			
			// Set first category as active tab if none selected
			if (cats.length > 0 && !activeTab) {
				setActiveTab(cats[0].id);
			}
		} catch (err) {
			console.error("Error loading categories:", err);
		}
	};

	const loadProductCategories = async (menuId) => {
		try {
			const data = await fetchCategoriesByMenu(menuId);
			setProductCategories(data || []);
		} catch (err) {
			console.error("Error loading product categories:", err);
		}
	};

	const loadProducts = async (pageNum, isInitial = false, search = "", category = activeCategory) => {
		try {
			setLoading(true);
			let data = [];
			const params = { 
				page: pageNum, 
				per_page: PER_PAGE, 
				search,
				category: category
			};

			if (!activeTab) return;
			data = await fetchProductsByMenu(activeTab, params);

			if (isInitial) {
				setProducts(data || []);
			} else {
				setProducts((prev) => [...prev, ...(data || [])]);
			}

			setHasMore(data && data.length === PER_PAGE);
			setError(null);
		} catch (err) {
			console.error("Error loading products:", err);
			setError("Failed to load products");
		} finally {
			setLoading(false);
		}
	};

	const handleLoadMore = () => {
		const nextPage = page + 1;
		setPage(nextPage);
		loadProducts(nextPage, false, searchQuery);
	};

	const formatDate = (dateString) => {
		if (!dateString) return "";
		const parts = dateString.split("-");
		if (parts.length !== 3) return dateString;
		const [year, month, day] = parts;
		return `${day}/${month}/${year}`;
	};

    const currentTabName = categories.find(c => String(c.id) === String(activeTab))?.name || "Category";


	return (
		<section className="most-ordered-browser">
			<div className="most-ordered-browser__container">
				{/* Tabs Navigation */}
				<div className="most-ordered-browser__tabs-container">
					<div className="most-ordered-browser__tabs">

						{categories.map((cat) => (
							<button
								key={cat.id}
								className={`most-ordered-browser__tab ${
									activeTab === cat.id ? "is-active" : ""
								}`}
								onClick={() => setActiveTab(cat.id)}
							>
								{cat.name}
							</button>
						))}
					</div>
				</div>

				<div className="most-ordered-browser__header">
					<div className="most-ordered-browser__utility">
						<a href={menuUrl || "#"} className="most-ordered-browser__see-more" target="_blank" rel="noopener noreferrer">
							See full menu <span>→</span>
						</a>

						<div className="most-ordered-browser__search">
							<div className="most-ordered-browser__search-box">
								<input
									type="text"
									placeholder="Search menu"
									value={searchQuery}
									onChange={(e) => setSearchQuery(e.target.value)}
								/>
							</div>
							<button type="button" className="most-ordered-browser__search-btn">
								<svg 
									width="24" 
									height="24" 
									viewBox="0 0 24 24" 
									fill="none" 
									xmlns="http://www.w3.org/2000/svg"
									aria-hidden="true"
								>
									<path 
										d="M21 21L15 15M17 10C17 13.866 13.866 17 10 17C6.13401 17 3 13.866 3 10C3 6.13401 6.13401 3 10 3C13.866 3 17 6.13401 17 10Z" 
										stroke="currentColor" 
										strokeWidth="3" 
										strokeLinecap="round" 
										strokeLinejoin="round"
									/>
								</svg>
							</button>
						</div>
					</div>

					<div className="most-ordered-browser__header-info">
						<h2 className="most-ordered-browser__title">
							{currentTabName}
						</h2>
						<p className="most-ordered-browser__description">
							The most commonly ordered items and dishes from the store
						</p>
					</div>

					{/* Product Category Filter */}
					{productCategories.length > 0 && (
						<div className="most-ordered-browser__cat-filter">
							<button 
								className={`most-ordered-browser__cat-item ${activeCategory === null ? 'is-active' : ''}`}
								onClick={() => setActiveCategory(null)}
							>
								All
							</button>
							{productCategories.map(cat => (
								<button 
									key={cat.id}
									className={`most-ordered-browser__cat-item ${activeCategory === cat.id ? 'is-active' : ''}`}
									onClick={() => setActiveCategory(cat.id)}
								>
									{cat.name}
								</button>
							))}
						</div>
					)}
				</div>

				{/* Products Content */}
				<div className="most-ordered-browser__content">
					{error ? (
						<div className="most-ordered-browser__error">{error}</div>
					) : products.length === 0 && !loading ? (
						<div className="most-ordered-browser__error">
							{sessionData.date 
								? `No products available in the ${currentTabName} category on ${formatDate(sessionData.date)}.`
								: `No products found in the ${currentTabName} category.`}
						</div>
					) : (
						<>
							<div className="most-ordered-browser__grid">
								{/* Show skeletons when loading initial data or switching tabs */}
								{loading && products.length === 0 ? (
									Array.from({ length: PER_PAGE }).map((_, i) => (
										<ProductSkeleton key={`skeleton-${i}`} />
									))
								) : (
									<>
										{products.map((product) => (
											<ProductCard key={product.id} product={product} menuId={activeTab} />
										))}
										{/* Show additional skeletons when loading more */}
										{loading && (
											Array.from({ length: 4 }).map((_, i) => (
												<ProductSkeleton key={`loading-more-${i}`} />
											))
										)}
									</>
								)}
							</div>

							{hasMore && !loading && (
								<div className="most-ordered-browser__pagination">
									<button
										onClick={handleLoadMore}
										className="most-ordered-browser__load-more"
									>
										Show More
									</button>
								</div>
							)}
						</>
					)}
				</div>
			</div>
		</section>
	);
}

function ProductCard({ product, menuId }) {
	return (
		<div className="most-ordered-browser__product">
			<div className="most-ordered-browser__product-image">
				<img src={product.image} alt={product.name} loading="lazy" />
			</div>

			<div className="most-ordered-browser__product-content">
				<div className="most-ordered-browser__product-info">
					<h3 className="most-ordered-browser__product-name">{product.name}</h3>
					<p className="most-ordered-browser__product-desc">
						PRODUCT DESCRIPTION
					</p>
					<div className="most-ordered-browser__product-price">
						${product.price.toFixed(2)}
					</div>
				</div>

				<a
					className="most-ordered-browser__add-btn zippy-button lightbox-zippy-btn"
					href="#lightbox-zippy-form"
					data-product-id={product.id}
					data-product_id={product.id}
					data-menu-id={menuId}
					data-product-sku={product.sku || ""}
					data-product-url={`?add-to-cart=${product.id}`}
					data-quantity="1"
					rel="nofollow"
					aria-label={`Add to cart: "${product.name}"`}
				>
					<span>+</span>
				</a>
			</div>
		</div>
	);
}

function ProductSkeleton() {
	return (
		<div className="most-ordered-browser__product most-ordered-browser__skeleton">
			<div className="skeleton-box skeleton-image"></div>
			<div className="most-ordered-browser__product-content">
				<div className="most-ordered-browser__product-info">
					<div className="skeleton-box skeleton-text"></div>
					<div className="skeleton-box skeleton-text" style={{ width: '60%' }}></div>
					<div className="skeleton-box skeleton-price"></div>
				</div>
				<div className="skeleton-box" style={{ width: '44px', height: '44px', borderRadius: '50%' }}></div>
			</div>
		</div>
	);
}
