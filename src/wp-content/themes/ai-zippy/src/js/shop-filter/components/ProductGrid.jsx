import { useRef, useEffect, useState } from "react";

export default function ProductGrid({ products, loading, viewMode, perPage }) {
	const containerRef = useRef(null);
	const lastHeightRef = useRef(0);

	useEffect(() => {
		if (!containerRef.current) return;

		if (loading) {
			const h = containerRef.current.offsetHeight;
			if (h > 0) {
				lastHeightRef.current = h;
				containerRef.current.style.minHeight = `${h}px`;
			}
		} else {
			const timer = setTimeout(() => {
				if (containerRef.current) {
					containerRef.current.style.minHeight = "";
				}
			}, 100);
			return () => clearTimeout(timer);
		}
	}, [loading]);

	const skeletonCount = perPage || 12;

	return (
		<div ref={containerRef} className="sf__grid-wrap">
			{loading && (
				<div className={`sf__grid-loading sf__grid-loading--${viewMode}`}>
					{Array.from({ length: skeletonCount }).map((_, i) => (
						<div key={i} className="sf__skeleton" />
					))}
				</div>
			)}

			{!loading && products.length === 0 && (
				<div className="sf__empty">
					<p>No products found matching your filters.</p>
				</div>
			)}

			{!loading && products.length > 0 && (
				<div className={`sf__grid sf__grid--${viewMode}`}>
					{products.map((product) => (
						<ProductCard key={product.id} product={product} viewMode={viewMode} />
					))}
				</div>
			)}
		</div>
	);
}

function ProductCard({ product, viewMode }) {
	const [activeThumb, setActiveThumb] = useState(0);
	const [wishlisted, setWishlisted] = useState(false);

	// Main image + gallery thumbnails
	const allImages = [product.image, ...(product.gallery || [])].filter(Boolean);
	const currentImage = allImages[activeThumb] || product.image;
	const extraCount = allImages.length > 3 ? allImages.length - 3 : 0;

	// Sale percentage
	const salePercent =
		product.on_sale && product.regular_price && product.sale_price
			? Math.round(
					((product.regular_price - product.sale_price) /
						product.regular_price) *
						100,
				)
			: 0;

	return (
		<div className="sf__card">
			{/* Image area */}
			<div className="sf__card-image">
				<a href={product.permalink}>
					<img src={currentImage} alt={product.name} loading="lazy" />
				</a>

				{/* Sale badge */}
				{product.on_sale && salePercent > 0 && (
					<span className="sf__badge sf__badge--sale">
						{salePercent}% OFF
					</span>
				)}
				{product.on_sale && salePercent === 0 && (
					<span className="sf__badge sf__badge--sale">Sale</span>
				)}

				{/* Out of stock */}
				{product.stock_status === "outofstock" && (
					<span className="sf__badge sf__badge--oos">Sold Out</span>
				)}

				{/* Wishlist */}
				<button
					className={`sf__card-wish ${wishlisted ? "is-active" : ""}`}
					onClick={() => setWishlisted(!wishlisted)}
					type="button"
					aria-label="Add to wishlist"
				>
					<svg
						width="18"
						height="18"
						viewBox="0 0 24 24"
						fill={wishlisted ? "currentColor" : "none"}
						stroke="currentColor"
						strokeWidth="2"
					>
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
					</svg>
				</button>

				{/* Gallery thumbnails */}
				{allImages.length > 1 && (
					<div className="sf__card-thumbs">
						{allImages.slice(0, 3).map((img, idx) => (
							<button
								key={idx}
								className={`sf__card-thumb ${idx === activeThumb ? "is-active" : ""}`}
								onMouseEnter={() => setActiveThumb(idx)}
								onClick={() => setActiveThumb(idx)}
								type="button"
							>
								<img src={img} alt="" />
							</button>
						))}
						{extraCount > 0 && (
							<a href={product.permalink} className="sf__card-thumb sf__card-thumb--more">
								+{extraCount}
							</a>
						)}
					</div>
				)}
			</div>

			{/* Product info */}
			<div className="sf__card-info">
				{product.categories.length > 0 && (
					<span className="sf__card-cat">
						{product.categories[0].name}
					</span>
				)}

				<a href={product.permalink} className="sf__card-title">
					{product.name}
				</a>

				{product.average_rating > 0 && (
					<div className="sf__card-rating">
						<Stars rating={product.average_rating} />
						<span className="sf__card-rating-count">
							({product.rating_count})
						</span>
					</div>
				)}

				{product.sku && viewMode === "list" && (
					<span className="sf__card-sku">SKU: {product.sku}</span>
				)}

				{viewMode === "list" && product.short_description && (
					<p className="sf__card-desc">{product.short_description}</p>
				)}

				{/* Price */}
				<div className="sf__card-pricing">
					<span
						className="sf__card-price"
						dangerouslySetInnerHTML={{ __html: product.price_html }}
					/>
				</div>

				{/* Add to cart + wishlist row */}
				<div className="sf__card-actions">
					{product.stock_status === "instock" && (
						<a
							href={product.add_to_cart_url}
							className="sf__card-btn"
							data-product-id={product.id}
						>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
								<circle cx="9" cy="21" r="1" />
								<circle cx="20" cy="21" r="1" />
								<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
							</svg>
							ADD TO CART
						</a>
					)}
					<button
						className={`sf__card-wish-sm ${wishlisted ? "is-active" : ""}`}
						onClick={() => setWishlisted(!wishlisted)}
						type="button"
						aria-label="Add to wishlist"
					>
						<svg
							width="16"
							height="16"
							viewBox="0 0 24 24"
							fill={wishlisted ? "currentColor" : "none"}
							stroke="currentColor"
							strokeWidth="2"
						>
							<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
						</svg>
					</button>
				</div>
			</div>
		</div>
	);
}

function Stars({ rating }) {
	const full = Math.floor(rating);
	const half = rating % 1 >= 0.5 ? 1 : 0;
	const empty = 5 - full - half;

	return (
		<span className="sf__stars">
			{"★".repeat(full)}
			{half ? "½" : ""}
			{"☆".repeat(empty)}
		</span>
	);
}
