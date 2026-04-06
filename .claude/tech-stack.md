# Technical Standards (Strict Rules)

1. **No jQuery:** All frontend blocks must use ReactJS via Gutenberg's `@wordpress/element`.
2. **Checkout/Cart:** For checkout page customization, use WooCommerce Store API. Never use legacy PHP hooks like `woocommerce_checkout_fields`.
3. **FSE Standards:** For page structure, prioritize `theme.json` and static HTML in `/templates/` over PHP templates.
4. **All code in English:** Comments, variables, function names — no Vietnamese in source code.
5. **Two build systems:**
   - **Vite** — Theme frontend assets (JS, SCSS) → `assets/dist/`
   - **@wordpress/scripts** — Custom Gutenberg blocks → `assets/blocks/`

### Project Structure
```
ai_zippy/                            # Project root
├── package.json                     # Dependencies + build scripts
├── vite.config.js                   # Vite config (theme assets)
├── bs.config.js                     # BrowserSync (auto-reload)
├── docker-compose.yml               # WordPress 6.8 + MySQL
├── Dockerfile                       # Production container
├── CLAUDE.md                        # 4-Agent protocol
│
└── src/wp-content/
    ├── themes/
    │   ├── ai-zippy/                # Parent theme (reusable core)
    │   │   ├── theme.json           # Design system (colors, typography, layout)
    │   │   ├── style.css            # WP theme header
    │   │   ├── functions.php        # Asset loading + block registration
    │   │   ├── templates/           # FSE page templates (index, page, single, 404, front-page)
    │   │   ├── parts/               # Template parts (header, footer)
    │   │   ├── src/
    │   │   │   ├── js/theme.js      # Main JS entry (Vite)
    │   │   │   ├── scss/            # SCSS source (Vite)
    │   │   │   │   ├── style.scss
    │   │   │   │   ├── _variables.scss
    │   │   │   │   ├── _base.scss
    │   │   │   │   ├── _header.scss
    │   │   │   │   ├── _homepage.scss
    │   │   │   │   └── _footer.scss
    │   │   │   └── blocks/          # Custom Gutenberg blocks (wp-scripts)
    │   │   │       └── hero-section/
    │   │   │           ├── block.json
    │   │   │           ├── index.js
    │   │   │           ├── edit.js
    │   │   │           ├── save.js
    │   │   │           ├── render.php
    │   │   │           ├── style.scss
    │   │   │           └── editor.scss
    │   │   └── assets/
    │   │       ├── dist/            # Vite build output
    │   │       └── blocks/          # wp-scripts build output
    │   │
    │   └── ai-zippy-child/          # Child theme (per-client overrides)
    │       ├── theme.json           # Override parent design tokens
    │       ├── style.css            # Theme header (Template: ai-zippy)
    │       ├── functions.php        # Client-specific PHP
    │       ├── templates/           # Override parent templates
    │       ├── parts/               # Override parent parts
    │       └── patterns/            # Client-specific block patterns
    │
    └── plugins/
        ├── zippy-core/              # Custom plugin (REST API, WooCommerce logic)
        ├── woocommerce/             # WooCommerce
        └── advanced-custom-fields-pro/
```

### Build Commands (from project root)
```bash
npm run dev          # Vite watch + wp-scripts watch + BrowserSync (localhost:3000)
npm run build        # Vite production + wp-scripts production
npm run build:blocks # wp-scripts only (blocks)
```

### Block Development Pattern
Each block in `src/blocks/{name}/` must contain:
- `block.json` — Metadata, attributes, supports
- `index.js` — Registration entry
- `edit.js` — Editor UI (Site Editor)
- `save.js` — Returns `null` (use server-side render)
- `render.php` — Frontend HTML output
- `style.scss` — Frontend + editor styles
- `editor.scss` — Editor-only styles
