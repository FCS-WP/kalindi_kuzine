import { useEffect, useState } from "react";
import { fetchMostOrdered, fetchCategories, fetchProductsByCategory } from "./api";
import "./style.scss";

export default function MostOrdered() {
	const [categories, setCategories] = useState([]);
	const [products, setProducts] = useState([]);
	const [activeTab, setActiveTab] = useState("most-ordered");
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [searchQuery, setSearchQuery] = useState("");
	const [page, setPage] = useState(1);
	const [hasMore, setHasMore] = useState(true);
	const PER_PAGE = 4;

	// Load categories on mount
	useEffect(() => {
		loadCategories();
	}, []);

	// Reset page and reload products when active tab changes
	useEffect(() => {
		setPage(1);
		loadProducts(1, true);
	}, [activeTab]);

	const loadCategories = async () => {
		try {
			const data = await fetchCategories();
			setCategories(data || []);
		} catch (err) {
			console.error("Error loading categories:", err);
		}
	};

	const loadProducts = async (pageNum, isInitial = false) => {
		try {
			setLoading(true);
			let data = [];
			const params = { page: pageNum, per_page: PER_PAGE };

			if (activeTab === "most-ordered") {
				data = await fetchMostOrdered(params);
			} else {
				data = await fetchProductsByCategory(activeTab, params);
			}

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
		loadProducts(nextPage);
	};

	const filteredProducts = products.filter((p) =>
		p.name.toLowerCase().includes(searchQuery.toLowerCase())
	);

	return (
		<section className="most-ordered-browser">
			<div className="most-ordered-browser__container">
				{/* Tabs Navigation */}
				<div className="most-ordered-browser__tabs-container">
					<div className="most-ordered-browser__tabs">
						<button
							className={`most-ordered-browser__tab ${
								activeTab === "most-ordered" ? "is-active" : ""
							}`}
							onClick={() => setActiveTab("most-ordered")}
						>
							Most Ordered
						</button>

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
					<div className="most-ordered-browser__header-info">
						<a href="#" className="most-ordered-browser__see-more">
							See full menu <span>→</span>
						</a>
						<h2 className="most-ordered-browser__title">
							{activeTab === "most-ordered" ? "Most Ordered" : categories.find(c => c.id === activeTab)?.name || "Category Products"}
						</h2>
						<p className="most-ordered-browser__description">
							The most commonly ordered items and dishes from the store
						</p>
					</div>

					<div className="most-ordered-browser__search">
						<div className="most-ordered-browser__search-box">
							<input
								type="text"
								placeholder="Search menu"
								value={searchQuery}
								onChange={(e) => setSearchQuery(e.target.value)}
							/>
							<button type="button" className="most-ordered-browser__search-btn">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
									<circle cx="11" cy="11" r="8"></circle>
									<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
								</svg>
							</button>
						</div>
					</div>
				</div>

				{/* Products Content */}
				<div className="most-ordered-browser__content">
					{error ? (
						<div className="most-ordered-browser__error">{error}</div>
					) : filteredProducts.length === 0 && !loading ? (
						<div className="most-ordered-browser__error">No products found matching "{searchQuery}"</div>
					) : (
						<>
							<div className="most-ordered-browser__grid">
								{filteredProducts.map((product) => (
									<ProductCard key={product.id} product={product} />
								))}
							</div>

							{loading && (
								<div className="most-ordered-browser__loading">Loading...</div>
							)}

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

function ProductCard({ product }) {
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
