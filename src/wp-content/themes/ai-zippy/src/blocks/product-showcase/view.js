/**
 * Frontend script for Product Showcase block.
 * Initializes Swiper for slider mode and thumbnail hover for all cards.
 */

document.addEventListener("DOMContentLoaded", () => {
	// Reveal blocks once ready
	document
		.querySelectorAll(".wp-block-ai-zippy-product-showcase")
		.forEach((block) => {
			block.classList.add("is-loaded");
		});

	// Thumbnail hover — switch main image
	document.querySelectorAll(".ps__card").forEach((card) => {
		const images = JSON.parse(card.dataset.images || "[]");
		const mainImg = card.querySelector(".ps__card-img");
		const thumbs = card.querySelectorAll(".ps__thumb[data-index]");

		if (!mainImg || images.length <= 1) return;

		thumbs.forEach((thumb) => {
			thumb.addEventListener("mouseenter", () => {
				const idx = parseInt(thumb.dataset.index, 10);
				if (images[idx]) {
					mainImg.src = images[idx];
					thumbs.forEach((t) => t.classList.remove("is-active"));
					thumb.classList.add("is-active");
				}
			});
		});
	});

	// Wishlist toggle
	document.querySelectorAll(".ps__wish, .ps__card-wish-sm").forEach((btn) => {
		btn.addEventListener("click", (e) => {
			e.preventDefault();
			btn.classList.toggle("is-active");
			const svg = btn.querySelector("svg");
			if (svg) {
				svg.setAttribute(
					"fill",
					btn.classList.contains("is-active") ? "currentColor" : "none",
				);
			}
		});
	});

	// Swiper init for slider blocks
	initSwipers();
});

async function initSwipers() {
	const sliders = document.querySelectorAll(
		".ps--slider[data-swiper-config]",
	);
	if (sliders.length === 0) return;

	// Dynamically import Swiper (loaded via CDN for caching)
	const [{ default: Swiper }, { Navigation, Pagination, Autoplay }] =
		await Promise.all([
			import("https://cdn.jsdelivr.net/npm/swiper@11/swiper.min.mjs"),
			import("https://cdn.jsdelivr.net/npm/swiper@11/modules/index.min.mjs"),
		]);

	// Inject Swiper CSS
	if (!document.querySelector('link[href*="swiper"]')) {
		const link = document.createElement("link");
		link.rel = "stylesheet";
		link.href =
			"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css";
		document.head.appendChild(link);
	}

	sliders.forEach((el) => {
		const config = JSON.parse(el.dataset.swiperConfig);
		const swiperEl = el.querySelector(".ps__swiper");
		if (!swiperEl) return;

		const options = {
			modules: [Navigation, Pagination, Autoplay],
			slidesPerView: 2,
			spaceBetween: 20,
			loop: true,
			pagination: {
				el: el.querySelector(".ps__pagination"),
				clickable: true,
			},
			navigation: {
				prevEl: el.querySelector(".ps__nav-prev"),
				nextEl: el.querySelector(".ps__nav-next"),
			},
			breakpoints: {
				480: { slidesPerView: 2 },
				768: { slidesPerView: Math.min(3, config.columns) },
				1024: { slidesPerView: config.columns },
			},
		};

		if (config.autoplay) {
			options.autoplay = {
				delay: config.autoplayDelay || 5000,
				disableOnInteraction: false,
			};
		}

		new Swiper(swiperEl, options);
	});
}
