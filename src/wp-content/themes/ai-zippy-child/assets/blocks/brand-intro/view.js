/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/*!*************************************************************************!*\
  !*** ./src/wp-content/themes/ai-zippy-child/blocks/brand-intro/view.js ***!
  \*************************************************************************/
__webpack_require__.r(__webpack_exports__);
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
  const [{
    default: Swiper
  }, {
    Autoplay,
    EffectFade
  }] = await Promise.all([import(/* webpackIgnore: true */"https://cdn.jsdelivr.net/npm/swiper@11/swiper.min.mjs"), import(/* webpackIgnore: true */"https://cdn.jsdelivr.net/npm/swiper@11/modules/index.min.mjs")]);

  // Inject Swiper CSS if not already present
  if (!document.querySelector('link[href*="swiper"]')) {
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = "https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css";
    document.head.appendChild(link);
  }
  sliders.forEach(el => {
    const swiperEl = el.querySelector(".bi__swiper");
    if (!swiperEl) return;
    new Swiper(swiperEl, {
      modules: [Autoplay, EffectFade],
      slidesPerView: 1,
      loop: true,
      effect: "fade",
      fadeEffect: {
        crossFade: true
      },
      autoplay: {
        delay: 4000,
        disableOnInteraction: false
      }
    });
  });
}
/******/ })()
;
//# sourceMappingURL=view.js.map