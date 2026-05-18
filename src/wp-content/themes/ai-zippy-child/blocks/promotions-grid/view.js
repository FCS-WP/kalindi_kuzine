if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initPromotionsSwipers);
} else {
	initPromotionsSwipers();
}

async function initPromotionsSwipers() {
	const sliders = document.querySelectorAll(
		".promotions-grid--slider[data-swiper-config]",
	);
	if (sliders.length === 0) return;

	// Dynamically import Swiper
	const [{ default: Swiper }, { Navigation, Pagination, Autoplay }] =
		await Promise.all([
			import(/* webpackIgnore: true */ "https://cdn.jsdelivr.net/npm/swiper@11/swiper.min.mjs"),
			import(/* webpackIgnore: true */ "https://cdn.jsdelivr.net/npm/swiper@11/modules/index.min.mjs"),
		]);

	// Inject Swiper CSS if not present
	if (!document.querySelector('link[href*="swiper"]')) {
		const link = document.createElement("link");
		link.rel = "stylesheet";
		link.href =
			"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css";
		document.head.appendChild(link);
	}

	sliders.forEach((el) => {
		const config = JSON.parse(el.dataset.swiperConfig);
		const swiperEl = el.querySelector(".promotions-grid__swiper");
		if (!swiperEl) return;

		const options = {
			modules: [Navigation, Pagination, Autoplay],
			slidesPerView: 1.2,
			spaceBetween: 10,
			loop: config.count > 1,
			pagination: {
				el: el.querySelector(".promotions-grid__pagination"),
				clickable: true,
			},
			navigation: {
				prevEl: el.querySelector(".promotions-grid__nav-prev"),
				nextEl: el.querySelector(".promotions-grid__nav-next"),
			},
			breakpoints: {
				480: { 
					slidesPerView: 2,
					spaceBetween: 15 
				},
				768: { 
					slidesPerView: 3,
					spaceBetween: 20 
				},
				1024: { 
					slidesPerView: 3,
					spaceBetween: 20 
				},
			},
		};

		if (config.autoplay) {
			options.autoplay = {
				delay: 4000,
				disableOnInteraction: false,
			};
		}

		new Swiper(swiperEl, options);
	});
}
