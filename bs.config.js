import browserSync from "browser-sync";
import { readFileSync } from "fs";

// Read PROJECT_HOST from .env
const envContent = readFileSync(".env", "utf-8");
const hostMatch = envContent.match(/PROJECT_HOST=(.+)/);
const wpUrl = hostMatch ? hostMatch[1].trim() : "http://localhost:24";
const themeDir = "src/wp-content/themes/ai-zippy";
const childDir = "src/wp-content/themes/ai-zippy-child";

browserSync.create().init({
	proxy: wpUrl,
	port: 3000,
	open: false,
	notify: false,

	files: [
		// CSS inject without reload
		`${themeDir}/assets/dist/css/**/*.css`,
		`${childDir}/assets/dist/css/**/*.css`,

		// Full reload on JS, PHP, HTML changes
		{
			match: [
				`${themeDir}/assets/dist/js/**/*.js`,
				`${themeDir}/**/*.php`,
				`${themeDir}/templates/**/*.html`,
				`${themeDir}/parts/**/*.html`,
				`${themeDir}/patterns/**/*.php`,
				`${childDir}/**/*.php`,
				`${childDir}/templates/**/*.html`,
				`${childDir}/parts/**/*.html`,
				`${childDir}/patterns/**/*.php`,
			],
			fn: function (event, file) {
				this.reload();
			},
		},
	],

	// Don't sync scroll/clicks across devices (optional)
	ghostMode: false,
});
