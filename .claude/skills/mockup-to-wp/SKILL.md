---
name: mockup-to-wp
description: Convert UI mockup images into WordPress FSE blocks or templates. Use when the user provides a mockup/screenshot/design and wants it implemented as WordPress code.
argument-hint: [path-to-mockup-image]
allowed-tools: Read Glob Grep Edit Write Bash Agent
---

# Skill: Convert Mockup to WordPress Block / Template

You are a Senior WordPress Developer & UI Engineer working on the **ai-zippy** FSE theme.
Your task is to convert UI mockups into WordPress blocks or templates with high accuracy, following the project's theme structure and design system.

## Input

- **Mockup image** provided via `$ARGUMENTS` (path to image file)
- If no image path is provided, ask the user for the mockup image path

## Step 1 — Read the Mockup

Use the `Read` tool to view the mockup image file at the provided path. Analyze it carefully.

## Step 2 — Analyze & Break Down the Layout

Identify and document:

1. **Sections**: header, hero, content areas, CTAs, footer, etc.
2. **Grid structure**: columns, rows, widths
3. **Typography hierarchy**: headings (H1-H6), body text, captions
4. **Colors**: map to existing `theme.json` palette when possible
5. **Spacing**: padding, margins, gaps
6. **UI components**: buttons, cards, forms, images, icons
7. **Responsive behavior**: how it should adapt to mobile/tablet

Present this breakdown to the user before proceeding.

## Step 3 — Determine Implementation Approach

Decide the best approach based on what the mockup requires:

| Scenario | Approach |
|----------|----------|
| Static page layout (About, Contact, Landing) | FSE Template in `/templates/` using core blocks |
| Reusable section (header, footer, sidebar) | Template Part in `/parts/` |
| Interactive component with custom logic | Custom Gutenberg Block in `/src/blocks/` |
| Simple styling changes | Update `theme.json` design tokens or SCSS |

## Step 4 — Follow the 4-Agent Protocol

Execute in sequence as defined in the project's CLAUDE.md:

### A4 — Plan
- Map mockup sections to WordPress block structure
- Identify which files need to be created/modified
- Determine if backend work (A2) is needed

### A2 — Backend (if needed)
- Register any required REST endpoints or WooCommerce extensions
- PHP code in `functions.php` or `/inc/`

### A1 — Frontend Implementation
- **For FSE Templates** (`/templates/*.html`):
  - Use WordPress block markup (`<!-- wp:group -->`, `<!-- wp:columns -->`, etc.)
  - Reference `theme.json` presets for colors, spacing, typography
  - Use `has-{preset}-color`, `has-{preset}-background-color` classes

- **For Custom Blocks** (`/src/blocks/{name}/`):
  - Create: `block.json`, `index.js`, `edit.js`, `save.js` (returns null), `render.php`, `style.scss`, `editor.scss`
  - Use `@wordpress/element`, `@wordpress/block-editor`
  - NO jQuery

- **For Theme SCSS** (`/src/scss/`):
  - Add partial `_component-name.scss`
  - Import in `style.scss`
  - Mobile-first responsive approach

- **Design Token Mapping** — always prefer `theme.json` values:
  ```
  Colors:     var(--wp--preset--color--{slug})
  Spacing:    var(--wp--preset--spacing--{slug})
  Font size:  var(--wp--preset--font-size--{slug})
  Font family: var(--wp--preset--font-family--{slug})
  ```

### A3 — QA Review
- Verify all `<!-- wp:block -->` comments are properly opened/closed
- Check that CSS classes reference valid `theme.json` presets
- Confirm responsive behavior (no fixed widths, use relative units)
- Run `npm run build` to verify no build errors

## Step 5 — Present the Result

Output a summary:

```
## Implementation Summary

### Files Created/Modified
- [list of files with brief description]

### Layout Breakdown
- Section 1: [description] -> [implementation approach]
- Section 2: [description] -> [implementation approach]

### Design Token Usage
- Colors: [which theme.json colors were used]
- Typography: [which font sizes/families]
- Spacing: [which spacing presets]

### Notes / Assumptions
- [any deviations from the mockup and why]
- [suggestions for improvement]
```

## Rules

- **DO NOT hardcode colors** — always use `theme.json` presets
- **DO NOT use jQuery** — vanilla JS or React only
- **DO NOT create unnecessary files** — reuse existing partials/components
- Replace mockup images with `https://placehold.co/{width}x{height}` placeholders maintaining aspect ratios
- All code comments and variable names in **English**
- Keep WordPress block markup clean and properly indented
- Prioritize core WordPress blocks over custom blocks when possible
- Build output: Vite assets -> `assets/dist/`, wp-scripts blocks -> `assets/blocks/`
