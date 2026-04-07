# BÁO CÁO KỸ THUẬT CHI TIẾT - AI ZIPPY THEME

> Phân tích source code thực tế để phục vụ phát triển ai-zippy-child
> Ngày: 2026-04-06

---

## MỤC LỤC

1. [Kiến trúc tổng thể](#1-kiến-trúc-tổng-thể)
2. [Flow hoạt động toàn bộ source](#2-flow-hoạt-động-toàn-bộ-source)
3. [Flow load CSS/JS](#3-flow-load-cssjs)
4. [Phân tích Block / Custom / React](#4-phân-tích-khi-nào-dùng-block--khi-nào-custom--khi-nào-react)
5. [Quy ước code và pattern](#5-quy-ước-code-và-pattern)
6. [AJAX/REST/API](#6-ajaxrestapi)
7. [Chiến lược code mới trong ai-zippy-child](#7-chiến-lược-code-mới-trong-ai-zippy-child)
8. [Sơ đồ file quan trọng](#8-sơ-đồ-file-quan-trọng)
9. [Hướng dẫn thực chiến](#9-hướng-dẫn-thực-chiến)
10. [Rủi ro và anti-pattern](#10-rủi-ro-và-anti-pattern)
11. [Kết luận](#11-kết-luận)

---

## 1. KIẾN TRÚC TỔNG THỂ

### 1.1 Cấu trúc thư mục

```
ai-zippy/
├── functions.php              # Entry point - định nghĩa constants + require loader
├── style.css                  # Theme header (chỉ metadata)
├── theme.json                 # Design tokens (single source of truth)
├── screenshot.png
│
├── inc/                       # PHP Classes (PSR-4 namespace: AiZippy\)
│   ├── loader.php             # Autoloader + Bootstrap tất cả modules
│   ├── setup/
│   │   └── dynamic-url.php    # Tự động detect URL cho local/tunnel dev
│   ├── Core/
│   │   ├── ViteAssets.php     # Đọc manifest + enqueue Vite assets
│   │   ├── ThemeSetup.php     # Theme supports + register blocks
│   │   ├── Cache.php          # Transient cache wrapper
│   │   └── RateLimiter.php    # IP-based rate limiting cho REST API
│   ├── Api/
│   │   └── ProductFilterApi.php   # REST: /ai-zippy/v1/products, /filter-options
│   ├── Hooks/
│   │   └── CacheInvalidation.php  # Clear cache khi product/category thay đổi
│   ├── Shop/
│   │   └── ShopAssets.php     # Enqueue React shop-filter app
│   ├── Cart/
│   │   └── CartAssets.php     # Enqueue React cart app
│   └── Checkout/
│       ├── CheckoutAssets.php     # Conditional enqueue (React vagy WC)
│       ├── CheckoutSettings.php   # WC Admin setting: chọn checkout template
│       ├── CheckoutShortcode.php  # [ai_zippy_checkout] shortcode
│       └── CheckoutValidation.php # Server-side validation cho checkout
│
├── templates/                 # FSE templates (HTML block markup)
│   ├── page-cart.html         # Cart page với #ai-zippy-cart
│   ├── page-checkout.html     # Checkout page với [ai_zippy_checkout]
│   ├── archive-product.html   # Shop archive với #ai-zippy-shop-filter
│   ├── single-product.html
│   └── ...
│
├── parts/                     # Template parts
│   ├── header.html            # Site header
│   └── footer.html            # Site footer
│
├── woocommerce/               # WC template overrides
│   └── checkout/
│       └── form-checkout.php  # Custom card-based checkout layout
│
├── src/                       # Source files (compile via Vite + wp-scripts)
│   ├── js/                    # JavaScript/React
│   │   ├── theme.js           # Main entry - imports modules + SCSS
│   │   ├── modules/           # Vanilla JS modules
│   │   │   ├── header.js      # Sticky header
│   │   │   ├── shop-view-toggle.js
│   │   │   ├── add-to-cart.js # AJAX add to cart
│   │   │   └── cart-api.js    # WC Store API wrapper
│   │   ├── shop-filter/       # React app: product filtering
│   │   ├── cart/              # React app: cart page
│   │   └── checkout/          # React app: checkout page
│   ├── scss/                  # Stylesheets
│   │   ├── style.scss         # Main entry (imports all partials)
│   │   ├── _variables.scss    # Design tokens + mixins
│   │   ├── wc-checkout-entry.scss  # WC classic checkout styles
│   │   └── _*.scss            # Component partials
│   └── blocks/                # Gutenberg blocks
│       ├── hero-section/
│       │   ├── block.json
│       │   ├── index.js       # Editor component
│       │   ├── render.php     # Server-side render
│       │   ├── style.scss
│       │   └── editor.scss
│       └── product-showcase/
│           └── ...
│
└── assets/                    # Compiled output
    ├── dist/                  # Vite output (JS, CSS)
    │   ├── js/
    │   ├── css/
    │   └── .vite/manifest.json
    └── blocks/                # wp-scripts output
        ├── hero-section/
        └── product-showcase/
```

### 1.2 File khởi động chính

**`functions.php`** (16 lines) - Minimal bootstrap:

```php
define('AI_ZIPPY_THEME_VERSION', '4.0.0');
define('AI_ZIPPY_THEME_DIR', get_template_directory());
define('AI_ZIPPY_THEME_URI', get_template_directory_uri());

require_once AI_ZIPPY_THEME_DIR . '/inc/loader.php';
```

**`inc/loader.php`** - Bootstrap sequence:
1. PSR-4 autoloader cho `AiZippy\` namespace
2. Load procedural files trong `inc/setup/`
3. Register các modules theo thứ tự ưu tiên

### 1.3 Theme Bootstrap Flow

```
WordPress loads theme
    ↓
functions.php
    ↓ define constants
    ↓ require loader.php
loader.php
    ↓ Register PSR-4 autoloader (AiZippy\ → inc/)
    ↓ Load inc/setup/*.php (dynamic-url.php)
    ↓ Register modules:
    ├─ ViteAssets::register()         → wp_enqueue_scripts
    ├─ ThemeSetup::register()         → after_setup_theme, init
    ├─ CacheInvalidation::register()  → product/category hooks
    ├─ ProductFilterApi::register()   → rest_api_init
    ├─ ShopAssets::register()         → wp_enqueue_scripts
    ├─ CartAssets::register()         → wp_enqueue_scripts
    ├─ CheckoutSettings::register()   → woocommerce_get_settings_advanced
    ├─ CheckoutShortcode::register()  → add_shortcode
    ├─ CheckoutValidation::register() → woocommerce_store_api_checkout_update_order_from_request
    └─ CheckoutAssets::register()     → wp_enqueue_scripts + AJAX handlers
```

---

## 2. FLOW HOẠT ĐỘNG TOÀN BỘ SOURCE

### 2.1 File chạy đầu tiên

```
functions.php → loader.php → Core\ViteAssets::register() → Core\ThemeSetup::register()
```

### 2.2 Hook/Action/Filter quan trọng

| Hook | Class | Chức năng |
|------|-------|-----------|
| `wp_enqueue_scripts` | `ViteAssets::enqueueTheme()` | Load theme.js + style.css |
| `wp_enqueue_scripts` | `ShopAssets::enqueue()` | Load shop-filter React app (is_shop) |
| `wp_enqueue_scripts` | `CartAssets::enqueue()` | Load cart React app (is_cart) |
| `wp_enqueue_scripts` | `CheckoutAssets::enqueue()` | Load checkout assets (conditional) |
| `script_loader_tag` | `ViteAssets::addModuleType()` | Add `type="module"` cho Vite scripts |
| `after_setup_theme` | `ThemeSetup::setup()` | Theme supports (blocks, WC, etc.) |
| `init` | `ThemeSetup::registerBlocks()` | Register Gutenberg blocks from assets/blocks |
| `block_categories_all` | `ThemeSetup::blockCategories()` | Add "AI Zippy" block category |
| `rest_api_init` | `ProductFilterApi::register()` | REST endpoints |
| `woocommerce_update_product` | `CacheInvalidation` | Clear cache khi product thay đổi |
| `woocommerce_get_settings_advanced` | `CheckoutSettings` | Add checkout template setting |

### 2.3 Flow render template (FSE)

```
Request → WordPress Template Hierarchy
    ↓
templates/page-cart.html (FSE template)
    ↓
<!-- wp:template-part {"slug":"header"} /-->
    ↓                     → parts/header.html
    ↓
<!-- wp:html -->
<div id="ai-zippy-cart"></div>
<!-- /wp:html -->
    ↓
React app mount vào #ai-zippy-cart (via CartAssets::enqueue)
    ↓
<!-- wp:template-part {"slug":"footer"} /-->
                          → parts/footer.html
```

### 2.4 Flow load header/footer/template part

FSE templates sử dụng block markup:
```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->
<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

WordPress tự động load từ `parts/header.html` và `parts/footer.html`.

### 2.5 Flow các tính năng custom

**Shop Filter (React App):**
```
templates/archive-product.html
    ↓ <div id="ai-zippy-shop-filter">
ShopAssets::enqueue() (is_shop || is_product_taxonomy)
    ↓
ViteAssets::enqueue('shop-filter')
    ↓
shop-filter/index.jsx → createRoot() mount vào container
    ↓
Fetch /wp-json/ai-zippy/v1/products
    ↓
ProductFilterApi::getProducts() → wc_get_products()
```

**Checkout (Dual Template):**
```
Admin chọn template trong WC Settings > Advanced
    ↓
CheckoutSettings::isReact() check
    ↓
CheckoutShortcode::render()
    ├─ React: <div id="ai-zippy-checkout">
    │          → CheckoutAssets::enqueueReactCheckout()
    │          → checkout/index.jsx mount
    └─ WooCommerce: do_blocks('<!-- wp:woocommerce/checkout /-->')
                    → form-checkout.php override
                    → CheckoutAssets::enqueueWcCheckout()
```

---

## 3. FLOW LOAD CSS/JS

### 3.1 Build System (Dual)

**Vite** (`vite.config.js`):
- Coffee theme JS/SCSS + React apps
- Output: `assets/dist/`
- Manifest: `assets/dist/.vite/manifest.json`

**@wordpress/scripts**:
- Coffee Gutenberg blocks
- Output: `assets/blocks/`

**Entry points:**
```javascript
// Vite entries
input: {
  theme: 'src/js/theme.js',           // Main theme JS + CSS
  style: 'src/scss/style.scss',       // CSS only
  'shop-filter': 'src/js/shop-filter/index.jsx',
  cart: 'src/js/cart/index.jsx',
  checkout: 'src/js/checkout/index.jsx',
  'wc-checkout': 'src/scss/wc-checkout-entry.scss', // CSS only
}
```

### 3.2 CSS Load Flow

```
ViteAssets::enqueue('ai-zippy-theme', 'src/.../theme.js')
    ↓ Đọc manifest.json
    ↓ Tìm entry, extract CSS files
    ↓
wp_enqueue_style() với version = filemtime()
```

**CSS Load Order:**
1. `style.scss` → imports `_variables.scss`, `_base.scss`, `_header.scss`, etc.
2. React app CSS bundled vào JS entry (Vite handles this)
3. `wc-checkout-entry.scss` → riêng cho WC classic checkout

### 3.3 JS Load Flow

```
ViteAssets::enqueueTheme()
    ↓
Enqueue theme.js (type="module")
    ↓
Add inline script: wcBlocksMiddlewareConfig (WC Store API nonce)
    ↓
theme.js imports:
    ├─ @scss/style.scss
    ├─ modules/header.js
    ├─ modules/shop-view-toggle.js
    └─ modules/add-to-cart.js
```

### 3.4 Frontend vs Admin vs Editor

| Context | Assets Loaded |
|---------|--------------|
| Frontend | `theme.js` + `style.css` (always) |
| Shop page | + `shop-filter` React app |
| Cart page | + `cart` React app |
| Checkout page | + `checkout` React OR `wc-checkout` CSS |
| Admin | WC Settings (CheckoutSettings adds field) |
| Editor | Block assets (`editorScript`, `editorStyle` in block.json) |

### 3.5 WC Store API Nonce

```php
// ViteAssets::enqueueTheme() line 30-38
wp_add_inline_script(
    'ai-zippy-theme',
    'var wcBlocksMiddlewareConfig = {
        storeApiNonce: "' . wp_create_nonce('wc_store_api') . '",
    };',
    'before'
);
```

Used by:
- `cart-api.js`
- `add-to-cart.js`
- React apps (cart, checkout)

---

## 4. PHÂN TÍCH KHI NÀO DÙNG BLOCK / KHI NÀO CUSTOM / KHI NÀO REACT

### 4.1 Gutenberg Blocks

**Files:** `src/blocks/*/`

**Khi nào dùng:**
- Contenteditable trong editor
- User có thể configure attributes
- Reusable blocks trong nhiều pages
- SEO quan trọng (server-side render)

**Cách register:**
```php
// ThemeSetup::registerBlocks()
foreach (glob(AI_ZIPPY_THEME_DIR . '/assets/blocks/*/block.json') as $block_json) {
    register_block_type(dirname($block_json));
}
```

**Block structure:**
```
hero-section/
├── block.json       # Metadata, attributes, scripts, styles
├── index.js         # Editor component (React)
├── edit.js          # Edit function
├── save.js          # Save (returns null for SSR)
├── render.php       # Server-side render (frontend)
├── style.scss       # Frontend styles
├── editor.scss      # Editor-only styles
└── view.js          # Frontend JS (optional)
```

**Asset load:**
- `editorScript` + `editorStyle`: Load trong editor
- `style`: Load trên frontend
- `viewScript`: Load trên frontend nếu block present

### 4.2 PHP Template Custom

**Files:** `woocommerce/checkout/form-checkout.php`, `inc/Checkout/CheckoutShortcode.php`

**Khi nào dùng:**
- WooCommerce template overrides
- Shortcode rendering
- Server-side logic phức tạp
- Không cần interactivity phức tạp

**Pattern:**
```php
// Shortcode
add_shortcode('ai_zippy_checkout', [CheckoutShortcode::class, 'render']);

// Template override
// woocommerce/checkout/form-checkout.php overrides WC template
```

### 4.3 React Mount

**Files:** `src/js/cart/`, `src/js/checkout/`, `src/js/shop-filter/`

**Khi nào dùng:**
- Complex state management (cart, checkout forms)
- Real-time updates
- Rich interactivity
- WC Store API integration

**Mounting pattern:**
```jsx
// index.jsx
const container = document.getElementById("ai-zippy-cart");
if (container) {
    const checkoutUrl = container.dataset.checkoutUrl;
    createRoot(container).render(<CartApp checkoutUrl={checkoutUrl} />);
}
```

**Container locations:**
- `#ai-zippy-cart` → templates/page-cart.html
- `#ai-zippy-checkout` → CheckoutShortcode (rendered)
- `#ai-zippy-shop-filter` → templates/archive-product.html

### 4.4 Shortcode

**File:** `CheckoutShortcode.php`

**Khi nào dùng:**
- Embed dynamic content trong FSE template
- Conditional rendering based on settings
- Bridge giữa PHP và React app

```php
public static function render(): string {
    if (CheckoutSettings::isReact()) {
        return '<div id="ai-zippy-checkout"></div>';
    }
    return do_blocks('<!-- wp:woocommerce/checkout /-->');
}
```

### 4.5 Decision Matrix

| Use Case | Technology | Reason |
|----------|------------|--------|
| Hero section, product showcase | Gutenberg Block | Editor configurable, reusable, SEO-friendly |
| Cart page, Checkout form | React App | Complex state, real-time updates, WC Store API |
| Product filtering, pagination | React App | Client-side filtering, instant feedback |
| Theme JS (header, add-to-cart) | Vanilla JS | Lightweight, no build complexity |
| WC checkout customization | PHP Template | WooCommerce hook integration |
| Simple PHP logic | Hook/Filter | No need for complex architecture |

---

## 5. QUY ƯỚC CODE VÀ PATTERN

### 5.1 Naming Convention

**PHP Classes & Namespaces:**
- Namespace: `AiZippy\{Subfolder}` → `AiZippy\Core\ViteAssets`
- File path: `inc/Core/ViteAssets.php`
- Static methods: `register()`, `enqueue()`, `getManifest()`

**CSS Class Prefixes:**
- `az-` → Theme general (`az-toast`, `az-checkout`)
- `sf-` → Shop filter (`sf__card`, `sf__filter`)
- `zc-` → Cart app (`zc-cart`, `zc-item`)
- `zk-` → Checkout app (`zk-section`, `zk-form`)
- `ps-` → Product showcase block (`ps__card`, `ps__grid`)

**JavaScript:**
- React components: PascalCase (`CartApp.jsx`)
- Modules: camelCase (`cart-api.js`, `add-to-cart.js`)
- Functions: camelCase exported (`initHeader`, `initAddToCart`)

### 5.2 Folder Organization

```
inc/
├── Core/         # Core functionality (assets, setup, cache)
├── Api/          # REST API endpoints
├── Hooks/        # WordPress hooks/filters
├── Shop/         # Shop-specific functionality
├── Cart/         # Cart-specific functionality
└── Checkout/     # Checkout-specific functionality

src/js/
├── theme.js      # Main entry
├── modules/      # Vanilla JS modules
├── shop-filter/  # React app
├── cart/         # React app
└── checkout/     # React app

src/scss/
├── style.scss    # Main entry
├── _variables.scss  # Design tokens
└── _*.scss       # Component partials
```

### 5.3 Reusable Components/Helpers

**PHP:**
- `ViteAssets::enqueue()` — Reusable Vite asset loader
- `Cache::get()`, `Cache::set()` — Transient wrapper
- `RateLimiter::isLimited()` — Rate limiting helper
- `ai_zippy_render_product_card()` — Product card renderer (in block render.php)

**JavaScript:**
- `cart-api.js` — WC Store API wrapper (used by add-to-cart, React apps)
- `_variables.scss` — SCSS mixins (`@include from(md)`, `@include truncate(2)`)

### 5.4 Cách mở rộng theme

**Override templates:**
```
ai-zippy-child/templates/page-cart.html
ai-zippy-child/parts/header.html
ai-zippy-child/woocommerce/checkout/form-checkout.php
```

**Extend via hooks:**
```php
// functions.php
add_action('wp_enqueue_scripts', function() {
    // Add custom scripts
});

add_filter('woocommerce_get_settings_advanced', function($settings) {
    // Add custom settings
});
```

**Override SCSS:**
```scss
// ai-zippy-child/src/scss/style.scss
@use "../../ai-zippy/src/scss/variables" as *;
// Custom overrides
```

**Add to Vite build:**
```javascript
// vite.config.js tự động detect child theme assets
const childJs = resolve(childSrc, 'js/child.js');
const childScss = resolve(childSrc, 'scss/style.scss');
if (existsSync(childJs)) input['child-theme'] = childJs;
if (existsSync(childScss)) input['child-style'] = childScss;
```

---

## 6. AJAX/REST/API

### 6.1 REST API Endpoints

**`ProductFilterApi.php`:**

| Endpoint | Method | Chức năng |
|----------|--------|-----------|
| `/wp-json/ai-zippy/v1/products` | GET | Search & filter products |
| `/wp-json/ai-zippy/v1/filter-options` | GET | Get categories, attributes, price range |

**Query params cho `/products`:**
- `search` — Search by name/SKU
- `category` — Comma-separated category slugs
- `min_price`, `max_price` — Price range
- `attributes` — Format: `pa_color:red,blue|pa_size:large`
- `stock_status` — instock/outofstock
- `orderby` — menu_order, date, price, popularity
- `order` — ASC/DESC
- `page`, `per_page` — Pagination

**Response format:**
```json
{
  "products": [...],
  "total": 100,
  "pages": 10,
  "page": 1,
  "per_page": 12
}
```

### 6.2 WC Store API (JavaScript)

**`cart-api.js`:**
```javascript
const STORE_API = "/wp-json/wc/store/v1";

addToCart(productId, quantity)    // POST /cart/add-item
updateCartItem(itemKey, quantity) // POST /cart/update-item
removeCartItem(itemKey)           // POST /cart/remove-item
getCart()                         // GET /cart
```

### 6.3 WordPress AJAX

**`CheckoutAssets.php`:**
```php
add_action('wp_ajax_az_update_checkout_qty', [self::class, 'ajaxUpdateQty']);
add_action('wp_ajax_nopriv_az_update_checkout_qty', [self::class, 'ajaxUpdateQty']);
```

**Usage trong form-checkout.php:**
```javascript
$.ajax({
    action: 'az_update_checkout_qty',
    cart_key: cartKey,
    quantity: newQty,
    security: '<?php echo wp_create_nonce("az-checkout-qty"); ?>'
});
```

### 6.4 Tích hợp thư viện ngoài

**React Libraries (package.json):**
```json
"dependencies": {
  "react": "^18.3.1",
  "react-dom": "^18.3.1",
  "react-international-phone": "^4.8.0"
}
```

**Swiper.js (CDN):** — Loaded dynamically in product-showcase block:
```php
// render.php check for slider mode
if ($display_style === 'slider') {
    wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js');
}
```

---

## 7. CHIẾN LƯỢC CODE MỚI TRONG AI-ZIPPY-CHILD

### 7.1 Khi nào dùng Hook/Filter

| Trường hợp | Ví dụ |
|------------|-------|
| Thêm settings | `add_filter('woocommerce_get_settings_advanced', ...)` |
| Thêm assets | `add_action('wp_enqueue_scripts', ...)` |
| Modify output | `add_filter('woocommerce_cart_item_name', ...)` |
| Validation | `add_action('woocommerce_checkout_process', ...)` |
| AJAX handlers | `add_action('wp_ajax_my_action', ...)` |

### 7.2 Khi nào Override Template

| Trường hợp | Ví dụ |
|------------|-------|
| Thay đổi WC layout | `woocommerce/checkout/form-checkout.php` |
| Thay đổi FSE template | `templates/page-cart.html` |
| Thay đổi header/footer | `parts/header.html`, `parts/footer.html` |

**Lưu ý:** Copy file từ parent, modify trong child theme.

### 7.3 Khi nào làm Block

| Trường hợp | Ví dụ |
|------------|-------|
| Component reusable trong editor | Custom CTA block |
| User cần configure attributes | Testimonial slider |
| SEO quan trọng | Server-side render |
| Complex editor UI | Block vớiInspector |

**Cách tạo:**
1. Tạo folder `src/blocks/my-block/`
2. Tạo `block.json`, `index.js`, `render.php`
3. Build với wp-scripts
4. Register trong `ThemeSetup::registerBlocks()` (child theme cần register riêng)

### 7.4 Khi nào dùng React

| Trường hợp | Ví dụ |
|------------|-------|
| Complex state management | Custom checkout flow |
| Real-time updates | Live product configurator |
| Rich interactivity | Advanced filter UI |
| WC Store API integration | Custom cart UI |

**Mounting pattern:**
```html
<!-- Trong template/shortcode -->
<div id="my-react-app" data-config='{"option": "value"}'></div>
```

```php
// Enqueue
ViteAssets::enqueue('my-app', 'src/js/my-app/index.jsx');
```

### 7.5 Khi nào chỉ nên dùng PHP

| Trường hợp | Ví dụ |
|------------|-------|
| Simple output | Shortcode hiển thị text |
| Server-side logic | Authentication check |
| WooCommerce hooks | Modify checkout fields |
| REST endpoints | Custom API |

### 7.6 File KHÔNG nên sửa ở parent theme

| File | Lý do |
|------|-------|
| `functions.php` | Update sẽ mất → Override trong child |
| `inc/loader.php` | Core bootstrap → Extend via hooks |
| `theme.json` | Design tokens → Override một phần trong child |
| `vite.config.js` | Build config → Child có config riêng |
| `templates/*.html` | Override bằng copy sang child |
| `parts/*.html` | Override bằng copy sang child |

### 7.7 Update-safe & Maintainable

**✅ DO:**
- Dùng hooks/filters trong child `functions.php`
- Override templates trong child theme
- Tạo modules mới trong `inc/` với namespace riêng
- Đăng ký assets mới với `ViteAssets::enqueue()`

**❌ DON'T:**
- Sửa trực tiếp parent theme files
- Hardcode paths (dùng constants `AI_ZIPPY_THEME_DIR`, `AI_ZIPPY_THEME_URI`)
- Global state không cần thiết
- Duplicate code từ parent

---

## 8. SƠ ĐỒ FILE QUAN TRỌNG

### 8.1 PHP Files

| File | Chức năng | Tầm quan trọng | Có thể override? |
|------|-----------|----------------|------------------|
| `functions.php` | Bootstrap constants + loader | ⭐⭐⭐ | Extend only (hooks) |
| `inc/loader.php` | PSR-4 autoloader + register modules | ⭐⭐⭐ | Không đụng |
| `inc/Core/ViteAssets.php` | Enqueue Vite-built assets | ⭐⭐⭐ | Extend via hooks |
| `inc/Core/ThemeSetup.php` | Theme supports + block registration | ⭐⭐⭐ | Extend via hooks |
| `inc/Core/Cache.php` | Transient wrapper | ⭐⭐ | Use directly |
| `inc/Core/RateLimiter.php` | Rate limiting | ⭐⭐ | Use directly |
| `inc/Api/ProductFilterApi.php` | REST API products | ⭐⭐ | Extend/modify via hooks |
| `inc/Shop/ShopAssets.php` | Shop filter enqueue | ⭐ | Extend pattern |
| `inc/Cart/CartAssets.php` | Cart app enqueue | ⭐ | Extend pattern |
| `inc/Checkout/CheckoutAssets.php` | Checkout conditional enqueue | ⭐⭐ | Extend via hooks |
| `inc/Checkout/CheckoutSettings.php` | WC admin setting | ⭐ | Extend pattern |
| `inc/Checkout/CheckoutShortcode.php` | Shortcode render | ⭐ | Override shortcode |
| `inc/Checkout/CheckoutValidation.php` | Server validation | ⭐ | Extend hooks |
| `inc/Hooks/CacheInvalidation.php` | Cache clearing | ⭐ | Extend hooks |
| `inc/setup/dynamic-url.php` | URL detection | ⭐ | Không cần đụng |

### 8.2 Template Files

| File | Chức năng | Có thể override? |
|------|-----------|------------------|
| `templates/page-cart.html` | Cart page template | ✅ Copy sang child |
| `templates/page-checkout.html` | Checkout page template | ✅ Copy sang child |
| `templates/archive-product.html` | Shop archive | ✅ Copy sang child |
| `templates/single-product.html` | Single product | ✅ Copy sang child |
| `parts/header.html` | Header template part | ✅ Copy sang child |
| `parts/footer.html` | Footer template part | ✅ Copy sang child |
| `woocommerce/checkout/form-checkout.php` | WC checkout override | ✅ Copy sang child |

### 8.3 Asset Entry Files

| File | Type | Chức năng |
|------|------|-----------|
| `src/js/theme.js` | JS Entry | Main theme JS (imports modules + SCSS) |
| `src/scss/style.scss` | CSS Entry | Main styles (imports all partials) |
| `src/js/shop-filter/index.jsx` | React Entry | Shop filter app |
| `src/js/cart/index.jsx` | React Entry | Cart app |
| `src/js/checkout/index.jsx` | React Entry | Checkout app |
| `src/scss/wc-checkout-entry.scss` | CSS Entry | WC classic checkout styles |

### 8.4 Block-related Files

| File | Chức năng |
|------|-----------|
| `src/blocks/hero-section/block.json` | Block config |
| `src/blocks/hero-section/index.js` | Editor component |
| `src/blocks/hero-section/render.php` | Server-side render |
| `src/blocks/hero-section/style.scss` | Frontend styles |
| `src/blocks/product-showcase/block.json` | Block config |
| `src/blocks/product-showcase/render.php` | Server-side render with `ai_zippy_render_product_card()` |

### 8.5 Build Config Files

| File | Chức năng |
|------|-----------|
| `package.json` | Scripts + dependencies |
| `vite.config.js` | Vite build config (theme JS/SCSS/React) |
| `bs.config.js` | BrowserSync config |
| `.env` | `PROJECT_HOST` variable |

### 8.6 Style Files

| File | Chức năng |
|------|-----------|
| `src/scss/_variables.scss` | Design tokens (colors, breakpoints, mixins) |
| `src/scss/_base.scss` | Reset, base styles |
| `src/scss/_header.scss` | Header styles |
| `src/scss/_shop.scss` | Shop page styles |
| `src/scss/_cart.scss` | Cart page styles |
| `src/scss/_checkout.scss` | Checkout styles |
| `src/scss/_add-to-cart.scss` | Add to cart button/toast |

---

## 9. HƯỚNG DẪN THỰC CHIẾN

### 9.1 Đặt PHP ở đâu

```
ai-zippy-child/
├── functions.php           # Child theme bootstrap
└── inc/
    └── MyFeature/
        └── MyClass.php     # PSR-4: AiZippyChild\MyFeature\MyClass
```

**functions.php example:**
```php
<?php
defined('ABSPATH') || exit;

define('AI_ZIPPY_CHILD_DIR', get_stylesheet_directory());
define('AI_ZIPPY_CHILD_URI', get_stylesheet_directory_uri());

// PSR-4 autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'AiZippyChild\\';
    if (!str_starts_with($class, $prefix)) return;
    
    $relative = substr($class, strlen($prefix));
    $file = AI_ZIPPY_CHILD_DIR . '/inc/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require_once $file;
});

// Register modules
AiZippyChild\MyFeature\MyClass::register();
```

### 9.2 Đặt CSS ở đâu

**Option 1: Child theme SCSS (recommended)**
```
ai-zippy-child/src/scss/style.scss
```
```scss
// Import parent variables
@use "../../ai-zippy/src/scss/variables" as *;

// Custom styles
.my-feature { ... }
```
Build automatic bởi Vite (vite.config.js đã support child theme).

**Option 2: Inline trong PHP**
```php
wp_enqueue_style('my-feature', AI_ZIPPY_CHILD_URI . '/assets/my-feature.css');
```

### 9.3 Đặt JS ở đâu

**Option 1: Vite entry (recommended)**
```
ai-zippy-child/src/js/child.js
```
```javascript
import "../scss/style.scss";
// Custom JS
```

**Option 2: Separate file**
```php
wp_enqueue_script('my-feature', AI_ZIPPY_CHILD_URI . '/assets/my-feature.js', [], '1.0', true);
```

### 9.4 Enqueue như nào

**Sử dụng ViteAssets (nếu build qua Vite):**
```php
// Vite đã compile child assets vào parent's manifest
// Không cần enqueue riêng
```

**Custom enqueue:**
```php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('child-style', AI_ZIPPY_CHILD_URI . '/style.css', ['ai-zippy-theme-css-0']);
    wp_enqueue_script('child-script', AI_ZIPPY_CHILD_URI . '/assets/child.js', [], '1.0', true);
});
```

### 9.5 Tạo block

1. Tạo folder:
```
ai-zippy-child/src/blocks/my-block/
├── block.json
├── index.js
├── render.php
├── style.scss
└── editor.scss
```

2. block.json:
```json
{
    "apiVersion": 3,
    "name": "ai-zippy-child/my-block",
    "title": "My Block",
    "category": "ai-zippy",
    "render": "file:./render.php"
}
```

3. Register trong child functions.php:
```php
add_action('init', function() {
    register_block_type(AI_ZIPPY_CHILD_DIR . '/src/blocks/my-block');
});
```

4. Build:
```bash
wp-scripts build --webpack-src-dir=src/blocks --output-path=assets/blocks
```

### 9.6 Tạo feature render bằng PHP

**Via shortcode:**
```php
// functions.php
add_shortcode('my_feature', function($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    ob_start();
    ?>
    <div class="my-feature" data-id="<?php echo esc_attr($atts['id']); ?>">
        <!-- Content -->
    </div>
    <?php
    return ob_get_clean();
});
```

**Via FSE block pattern:**
```php
// Register pattern
add_action('init', function() {
    register_block_pattern('ai-zippy-child/my-pattern', [
        'title' => 'My Pattern',
        'categories' => ['ai-zippy'],
        'content' => '<!-- wp:html --><div class="my-feature"></div><!-- /wp:html -->',
    ]);
});
```

### 9.7 Tạo feature kiểu React mount

1. Tạo React app:
```
ai-zippy-child/src/js/my-feature/
├── index.jsx
├── MyApp.jsx
└── my-feature.scss
```

2. index.jsx:
```jsx
import { createRoot } from "react-dom/client";
import MyApp from "./MyApp";
import "./my-feature.scss";

const container = document.getElementById("my-feature-app");
if (container) {
    createRoot(container).render(<MyApp config={JSON.parse(container.dataset.config || '{}')} />);
}
```

3. Thêm vào Vite entry (tự động nếu file tồn tại):
```javascript
// vite.config.js đã detect childJs
input['child-theme'] = childJs;
```

4. Template:
```html
<div id="my-feature-app" data-config='{"option": "value"}'></div>
```

5. Enqueue:
```php
add_action('wp_enqueue_scripts', function() {
    if (is_page('my-page')) {
        AiZippy\Core\ViteAssets::enqueue(
            'ai-zippy-child-my-feature',
            'src/wp-content/themes/ai-zippy-child/src/js/my-feature/index.jsx'
        );
    }
});
```

### 9.8 Tránh conflict với parent theme

1. **Namespace riêng:** `AiZippyChild\` thay vì `AiZippy\`
2. **CSS prefix:** `azc-` thay vì `az-`
3. **Database options:** Prefix `ai_zippy_child_`
4. **REST endpoints:** Namespace `ai-zippy-child/v1`
5. **Không override PHP class:** Tạo class mới, extend nếu cần

---

## 10. RỦI RO VÀ ANTI-PATTERN

### 10.1 Logic Coupling Chặt

**Vấn đề:**
- `CheckoutAssets` depends on `CheckoutSettings`
- `ShopAssets`, `CartAssets` use `ViteAssets::enqueue()`
- React apps depend on nonce từ `ViteAssets`

**Giải pháp:**
- Giữ nguyên dependency chain
- Đừng tạo circular dependencies
- Extend thay vì modify

### 10.2 Hardcode Path

**❌ Không nên:**
```php
require_once '/var/www/wp-content/themes/ai-zippy/inc/loader.php';
```

**✅ Nên:**
```php
require_once AI_ZIPPY_THEME_DIR . '/inc/loader.php';
// Hoặc trong child:
require_once AI_ZIPPY_CHILD_DIR . '/inc/MyFeature/MyClass.php';
```

### 10.3 Global State

**Vấn đề:**
- `ViteAssets::$manifest` là static property
- WC Store API nonce được set inline

**Giải pháp:**
- Đừng modify global state trực tiếp
- Dùng hooks để extend

### 10.4 Dependency Ngầm Giữa Template

**Vấn đề:**
- `page-cart.html` depends on `#ai-zippy-cart`
- `archive-product.html` depends on `#ai-zippy-shop-filter`
- Shortcode `ai_zippy_checkout` outputs different HTML based on settings

**Giải pháp:**
- Nếu override template, giữ nguyên container IDs
- Document container requirements

### 10.5 Lỗi Thường Gặp Khi Load Asset

| Lỗi | Nguyên nhân | Giải pháp |
|-----|-------------|-----------|
| JS không load | Entry name sai trong ViteAssets | Check manifest.json key |
| `type="module"` missing | Handle không match `ai-zippy-*` | Rename handle |
| CSS không load | Entry không có CSS | Check manifest `css` array |
| WC Store API 403 | Nonce missing | Check `wcBlocksMiddlewareConfig` |

### 10.6 Rủi Ro Khi Update Parent Theme

| File | Rủi ro khi update | Mitigation |
|------|-------------------|------------|
| `ViteAssets.php` | Entry key format thay đổi | Check manifest format |
| `loader.php` | Register order thay đổi | Re-check hooks priority |
| `theme.json` | Colors/breakpoints thay đổi | Test SCSS variables |
| Block `render.php` | Markup thay đổi | Test visual regression |
| React apps | DOM structure thay đổi | Test E2E |

---

## 11. KẾT LUẬN

### 11.1 Chiến lược tốt nhất để phát triển trên ai-zippy-child

1. **Hooks-based approach:** Mọi tính năng mới nên dùng hooks/filters trong `functions.php`
2. **Tên riêng:** Prefix classes, options, endpoints với `ai_zippy_child_` hoặc `AiZippyChild\`
3. **Vite sẵn sàng:** Vite config đã support child theme, chỉ cần tạo file)
4. **Document IDs:** Nếu tạo template mới, document container IDs cần thiết cho React apps

### 11.2 Khi nào dùng Block

- ✅ Content editor có thể configure
- ✅ Reusable trong nhiều pages
- ✅ Cần server-side render (SEO)
- ✅ UI phức tạp trong editor (Inspector controls)

### 11.3 Khi nào dùng PHP Template

- ✅ WooCommerce template overrides
- ✅ Shortcode rendering
- ✅ Server-side logic không cần JS interactivity
- ✅ Integration với WordPress hooks/filters

### 11.4 Khi nào dùng React

- ✅ Complex state management
- ✅ Real-time updates (cart, filtering)
- ✅ Rich interactivity
- ✅ WC Store API integration
- ❌ SEO-critical content (use SSR instead)

### 11.5 Hướng code an toàn, dễ maintain nhất

**Ưu tiên theo thứ tự:**

1. **Hooks/Filters** — Ít rủi ro nhất, update-safe
2. **Template override** — Copy file từ parent, modify trong child
3. **New modules** — Tạo class mới với namespace riêng
4. **New blocks** — Tạo block mới (không modify parent blocks)
5. **React apps** — Tạo app mới với container ID riêng
6. **Override parent classes** — Chỉ khi không có cách nào khác

**Tránh tuyệt đối:**
- ❌ Sửa trực tiếp parent theme files
- ❌ Duplicate code thay vì extend
- ❌ Hardcode paths/URLs
- ❌ Global state manipulation
- ❌ Breaking container IDs trong templates

---

## PHỤ LỤC: FILE PATHS TÓM TẮT

### PHP Classes
```
inc/loader.php                           # PSR-4 autoloader + bootstrap
inc/Core/ViteAssets.php                  # Vite manifest + enqueue
inc/Core/ThemeSetup.php                  # Theme supports + blocks
inc/Core/Cache.php                       # Transient wrapper
inc/Core/RateLimiter.php                 # IP rate limiting
inc/Api/ProductFilterApi.php             # REST: products, filter-options
inc/Hooks/CacheInvalidation.php          # Clear cache trên product change
inc/Shop/ShopAssets.php                  # Shop filter React app enqueue
inc/Cart/CartAssets.php                  # Cart React app enqueue
inc/Checkout/CheckoutAssets.php          # Checkout conditional enqueue
inc/Checkout/CheckoutSettings.php        # WC admin setting
inc/Checkout/CheckoutShortcode.php       # [ai_zippy_checkout] shortcode
inc/Checkout/CheckoutValidation.php      # Server-side validation
inc/setup/dynamic-url.php                # URL detection cho dev
```

### Templates
```
templates/page-cart.html                 # Cart page (#ai-zippy-cart)
templates/page-checkout.html             # Checkout page ([ai_zippy_checkout])
templates/archive-product.html           # Shop archive (#ai-zippy-shop-filter)
parts/header.html                        # Header template part
parts/footer.html                        # Footer template part
```

### React Apps
```
src/js/theme.js                          # Main entry (vanilla JS modules)
src/js/shop-filter/index.jsx             # Shop filter app
src/js/cart/index.jsx                    # Cart app
src/js/checkout/index.jsx                # Checkout app
src/js/modules/header.js                 # Sticky header
src/js/modules/add-to-cart.js            # AJAX add to cart
src/js/modules/cart-api.js               # WC Store API wrapper
```

### SCSS
```
src/scss/style.scss                      # Main entry (imports all)
src/scss/_variables.scss                 # Design tokens + mixins
src/scss/_base.scss                      # Reset, base
src/scss/_header.scss                    # Header
src/scss/_shop.scss                      # Shop page
src/scss/_cart.scss                      # Cart page
src/scss/_checkout.scss                  # Checkout
src/scss/wc-checkout-entry.scss          # WC classic checkout
```

### Build
```
package.json                             # Scripts + dependencies
vite.config.js                           # Vite config
bs.config.js                             # BrowserSync
.env                                     # PROJECT_HOST
```

---

*Báo cáo được tạo tự động dựa trên source code thực tế của ai-zippy theme version 4.0.0*