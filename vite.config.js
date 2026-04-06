import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { resolve } from "path";
import { existsSync } from "fs";

// Theme paths
const themesDir = resolve(__dirname, "src/wp-content/themes");
const parentDir = resolve(themesDir, "ai-zippy");
const parentSrc = resolve(parentDir, "src");
const childDir = resolve(themesDir, "ai-zippy-child");
const childSrc = resolve(childDir, "src");

// Vite handles: theme JS + SCSS only
// Blocks are handled by @wordpress/scripts (separate build)
const input = {
	theme: resolve(parentSrc, "js/theme.js"),
	style: resolve(parentSrc, "scss/style.scss"),
	"shop-filter": resolve(parentSrc, "js/shop-filter/index.jsx"),
	cart: resolve(parentSrc, "js/cart/index.jsx"),
	checkout: resolve(parentSrc, "js/checkout/index.jsx"),
	"wc-checkout": resolve(parentSrc, "scss/wc-checkout-entry.scss"),
};

// Child theme assets (only if source files exist)
const childJs = resolve(childSrc, "js/child.js");
const childScss = resolve(childSrc, "scss/style.scss");

if (existsSync(childJs)) {
	input["child-theme"] = childJs;
}
if (existsSync(childScss)) {
	input["child-style"] = childScss;
}

export default defineConfig({
	plugins: [react()],

	build: {
		outDir: resolve(parentDir, "assets/dist"),
		emptyOutDir: true,

		// Watch mode for `npm run dev`
		watch: process.env.NODE_ENV === "development" ? {} : null,

		rollupOptions: {
			input,
			output: {
				entryFileNames: "js/[name].js",
				chunkFileNames: "js/chunks/[name]-[hash].js",
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith(".css")) {
						return "css/[name].css";
					}
					return "assets/[name]-[hash][extname]";
				},
			},
		},

		manifest: true,
		sourcemap: true,
	},

	css: {
		preprocessorOptions: {
			scss: {
				api: "modern-compiler",
			},
		},
	},

	resolve: {
		alias: {
			"@": parentSrc,
			"@scss": resolve(parentSrc, "scss"),
			"@child": childSrc,
		},
	},
});
