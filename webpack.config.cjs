const defaultConfig = require("@wordpress/scripts/config/webpack.config");

module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules.map((rule) => {
				if (rule.test && rule.test.toString().includes("scss")) {
					return {
						...rule,
						use: rule.use.map((loader) => {
							if (
								typeof loader === "object" &&
								loader.loader &&
								loader.loader.includes("sass-loader")
							) {
								return {
									...loader,
									options: {
										...loader.options,
										sassOptions: {
											...loader.options.sassOptions,
											silenceDeprecations: ["import", "global-builtin"],
										},
									},
								};
							}
							return loader;
						}),
					};
				}
				return rule;
			}),
		],
	},
};
