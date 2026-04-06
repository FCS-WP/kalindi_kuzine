# Technical Standards

## Core Principles
1. **No jQuery** — Use React (`@wordpress/element`) or vanilla JS
2. **All code in English** — Comments, variables, function names
3. **PHP in PSR-4 classes** — Never put logic in `functions.php`
4. **Design tokens from theme.json** — Never hardcode colors
5. **WC Store API** for client-side cart/checkout — No legacy PHP hooks

## Build Systems
- **Vite** — Theme JS/SCSS + React apps → `assets/dist/`
- **@wordpress/scripts** — Gutenberg blocks → `assets/blocks/`

## Rules (see `.claude/rules/` for details)
- [blocks.md](rules/blocks.md) — Gutenberg block development patterns
- [php-classes.md](rules/php-classes.md) — PSR-4 architecture, loader, asset enqueue
- [scss-css.md](rules/scss-css.md) — Variables, mixins, class prefixes, conditional loading
- [fse-templates.md](rules/fse-templates.md) — FSE templates, WC overrides, wpautop fixes
