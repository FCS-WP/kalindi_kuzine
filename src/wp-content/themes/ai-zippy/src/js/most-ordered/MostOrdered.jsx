import { useEffect, useState } from "react";
import { fetchMostOrdered, fetchCategories, fetchProductsByCategory } from "./api";
import "./style.scss";

export default function MostOrdered() {
	const [categories, setCategories] = useState([]);
	const [products, setProducts] = useState([]);
	const [activeTab, setActiveTab] = useState("most-ordered");
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	// Load categories and products on mount
	useEffect(() => {
		loadCategories();
		loadMostOrdered();
	}, []);

	// Load products when active tab changes
	useEffect(() => {
		if (activeTab === "most-ordered") {
			loadMostOrdered();
		} else {
			loadProductsByCategory(activeTab);
		}
	}, [activeTab]);

	const loadCategories = async () => {
		try {
			const data = await fetchCategories();
			setCategories(data || []);
		} catch (err) {
			console.error("Error loading categories:", err);
		}
	};

	const loadMostOrdered = async () => {
		try {
			setLoading(true);
			const data = await fetchMostOrdered({ limit: 4 });
			setProducts(data || []);
			setError(null);
		} catch (err) {
			console.error("Error loading products:", err);
			setError("Failed to load products");
		} finally {
			setLoading(false);
		}
	};

	const loadProductsByCategory = async (categoryId) => {
		try {
			setLoading(true);
			const data = await fetchProductsByCategory(categoryId, { limit: 4 });
			setProducts(data || []);
			setError(null);
		} catch (err) {
			console.error("Error loading products:", err);
			setError("Failed to load products");
		} finally {
			setLoading(false);
		}
	};

	return (
		<section className="most-ordered-browser">
			{/* Tabs Navigation */}
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

				<a href="#" className="most-ordered-browser__see-more">
					See full menu <span>→</span>
				</a>
			</div>

			{/* Products Content */}
			<div className="most-ordered-browser__content">
				<h2 className="most-ordered-browser__title">
					{activeTab === "most-ordered" ? "Most Ordered" : ""}
				</h2>
				<p className="most-ordered-browser__description">
					The most commonly ordered items and dishes from the store
				</p>

				{loading ? (
					<div className="most-ordered-browser__loading">Loading products...</div>
				) : error ? (
					<div className="most-ordered-browser__error">{error}</div>
				) : !products || products.length === 0 ? (
					<div className="most-ordered-browser__error">No products found</div>
				) : (
					<div className="most-ordered-browser__grid">
						{products.map((product) => (
							<ProductCard key={product.id} product={product} />
						))}
					</div>
				)}
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
				<h3 className="most-ordered-browser__product-name">{product.name}</h3>
				<p className="most-ordered-browser__product-desc">
					PRODUCT DESCRIPTION
				</p>

				<div className="most-ordered-browser__product-footer">
					<div className="most-ordered-browser__product-price">
						${product.price.toFixed(2)}
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
		</div>
	);
}
