/**
 * AI Zippy Theme — Main Entry Point
 *
 * This file only imports styles and initializes modules.
 * All logic lives in /modules/*.js
 */

import "@scss/style.scss";

import { initHeader } from "./modules/header.js";
import { initShopViewToggle } from "./modules/shop-view-toggle.js";
import { initAddToCart } from "./modules/add-to-cart.js";

document.addEventListener("DOMContentLoaded", () => {
	initHeader();
	initShopViewToggle();
	initAddToCart();
});
