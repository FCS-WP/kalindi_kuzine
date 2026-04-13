import defaultConfig from "@wordpress/scripts/config/webpack.config.js";

const config = {
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

export default config;
