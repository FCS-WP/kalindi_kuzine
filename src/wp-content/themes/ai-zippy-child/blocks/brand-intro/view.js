/**
 * Frontend script for Brand Intro block.
 * Initializes Swiper for hero slider.
 */

document.addEventListener("DOMContentLoaded", () => {
  initBrandIntroSwipers();
});

async function initBrandIntroSwipers() {
  const sliders = document.querySelectorAll(".bi--has-slider");
  if (sliders.length === 0) return;

  // Dynamically import Swiper
  const [{ default: Swiper }, { Autoplay, EffectFade }] = await Promise.all([
    import(
      /* webpackIgnore: true */ "https://cdn.jsdelivr.net/npm/swiper@11/swiper.min.mjs"
    ),
    import(
      /* webpackIgnore: true */ "https://cdn.jsdelivr.net/npm/swiper@11/modules/index.min.mjs"
    ),
  ]);

  // Inject Swiper CSS if not already present
  if (!document.querySelector('link[href*="swiper"]')) {
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = "https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css";
    document.head.appendChild(link);
  }

  sliders.forEach((el) => {
    const swiperEl = el.querySelector(".bi__swiper");
    if (!swiperEl) return;

    new Swiper(swiperEl, {
      modules: [Autoplay, EffectFade],
      slidesPerView: 1,
      loop: true,
      effect: "fade",
      fadeEffect: {
        crossFade: true,
      },
      autoplay: {
        delay: 4000,
        disableOnInteraction: false,
      },
    });
  });
}
