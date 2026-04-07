# 📋 React Feature Implementation Flow

**Quy trình thêm một tính năng React mới vào WordPress Theme (AI Zippy)**

---

## 📐 Quy Trình Chung

```
1️⃣ Backend API Setup
   ↓
2️⃣ Frontend React Structure  
   ↓
3️⃣ Vite Build Configuration
   ↓
4️⃣ Gutenberg Block Wrapper
   ↓
5️⃣ Asset Enqueueing
   ↓
6️⃣ Testing & Debugging
```

---

## 1️⃣ Backend API Setup

### Thư mục: `src/wp-content/themes/ai-zippy/inc/Api/`

Tạo PHP class để handle REST API endpoints.

**File**: `YourFeatureApi.php`
```php
<?php

namespace AiZippy\Api;

class YourFeatureApi {
    public static function register() {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes() {
        register_rest_route('ai-zippy/v1', '/your-endpoint', [
            'methods' => 'GET',
            'callback' => [self::class, 'handleRequest'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handleRequest(\WP_REST_Request $request) {
        // Return data as JSON
        return rest_ensure_response([
            // your data
        ]);
    }
}
```

**Bước:**
1. Tạo file class mới trong `inc/Api/`
2. Implement `register()` static method
3. Hook vào `rest_api_init` action
4. Register route với `register_rest_route()`
5. Return JSON response

---

## 2️⃣ Frontend React Structure

### Thư mục: `src/wp-content/themes/ai-zippy/src/js/{feature-name}/`

**Tạo cấu trúc sau:**

```
src/js/most-ordered/
├── index.jsx              # Mounting point (DOM)
├── MostOrdered.jsx        # Main component
├── api.js                 # API fetch functions
├── style.scss             # Component styles
└── (optional) subcomponents/
```

### **index.jsx** - Mounting Point
```jsx
import { createRoot } from "react-dom/client";
import MostOrdered from "./MostOrdered.jsx";

function initMostOrdered() {
    const container = document.getElementById("ai-zippy-most-ordered");
    if (container) {
        createRoot(container).render(<MostOrdered />);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMostOrdered);
} else {
    initMostOrdered();
}

setTimeout(initMostOrdered, 0);
```

**Key Points:**
- Tên container ID: `ai-zippy-{feature-name}`
- Use `createRoot()` từ `react-dom/client`
- Check `document.readyState` để ensure DOM ready
- Add `setTimeout` fallback

### **MostOrdered.jsx** - Main Component
```jsx
import { useEffect, useState } from "react";
import { fetchData } from "./api";
import "./style.scss";

export default function MostOrdered() {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        try {
            const result = await fetchData();
            setData(result);
        } catch (err) {
            console.error("Error:", err);
        } finally {
            setLoading(false);
        }
    };

    return (
        <section className="feature-name">
            {/* JSX here */}
        </section>
    );
}
```

### **api.js** - API Calls
```jsx
const API_BASE = "/wp-json/ai-zippy/v1";

export async function fetchData(params = {}) {
    const query = new URLSearchParams(params);
    const response = await fetch(`${API_BASE}/your-endpoint?${query}`);
    
    if (!response.ok) {
        throw new Error(`API Error: ${response.status}`);
    }
    
    return response.json();
}
```

### **style.scss** - Styles
```scss
.feature-name {
    // Styles here
}

.feature-name__element {
    // BEM naming convention
}
```

**Naming Convention:** `{feature-name}__element--modifier`

---

## 3️⃣ Vite Build Configuration

### File: `vite.config.js` (Project Root)

**Add entry point:**
```js
export default defineConfig({
    build: {
        rollupOptions: {
            input: {
                // existing entries...
                'src/wp-content/themes/ai-zippy/src/js/most-ordered/index': 
                    './src/wp-content/themes/ai-zippy/src/js/most-ordered/index.jsx',
            },
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].chunk.js',
                assetFileNames: ({ name }) => {
                    if (name && name.endsWith('.css')) {
                        return 'css/[name]';
                    }
                    return 'assets/[name]';
                },
            },
        },
    },
});
```

**Manifest Key:** `src/wp-content/themes/ai-zippy/src/js/most-ordered/index`

---

## 4️⃣ Gutenberg Block Wrapper

### Thư mục: `src/wp-content/themes/ai-zippy-child/blocks/{feature-name}/`

**Tạo cấu trúc:**

```
blocks/most-ordered/
├── block.json          # Block metadata
├── index.js            # Entry point (required by wp-scripts)
├── edit.js             # Editor UI
├── save.js             # Frontend (return null for SSR)
├── render.php          # Server-side output
├── style.scss          # Frontend styles
└── editor.scss         # Editor styles
```

### **block.json** - Metadata
```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "ai-zippy-child/most-ordered",
  "title": "Most Ordered Browser",
  "category": "ai-zippy",
  "icon": "store",
  "supports": {
    "align": ["wide", "full"],
    "anchor": true
  },
  "attributes": {
    "limit": {
      "type": "number",
      "default": 4
    }
  }
}
```

### **index.js** - Entry
```js
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import save from './save';
import './editor.scss';

registerBlockType('ai-zippy-child/most-ordered', {
    edit: Edit,
    save,
});
```

### **edit.js** - Editor UI
```jsx
import { InspectorControls } from '@wordpress/block-editor';
import { RangeControl, Panel, PanelBody } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const { limit } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title="Settings">
                    <RangeControl
                        label="Products to display"
                        value={limit}
                        onChange={(val) => setAttributes({ limit: val })}
                        min={1}
                        max={12}
                    />
                </PanelBody>
            </InspectorControls>
            
            <div>Block preview here</div>
        </>
    );
}
```

### **save.js** - Frontend (SSR)
```js
export default function save() {
    // Return null để cho render.php handle output
    return null;
}
```

### **render.php** - Server Output
```php
<?php
/**
 * Most Ordered Block Render
 */

$limit = isset($attributes['limit']) ? (int)$attributes['limit'] : 4;

// Enqueue React app
\AiZippy\Core\ViteAssets::enqueue(
    'ai-zippy-most-ordered',
    [
        'type' => 'js',
        'deps' => ['wp-element'],
    ]
);

// Output container
echo '<div id="ai-zippy-most-ordered" class="ai-zippy-most-ordered-block"></div>';
?>
```

---

## 5️⃣ Asset Enqueueing

### Thư mục: `src/wp-content/themes/ai-zippy/inc/`

**Tạo class:** `YourFeatureAssets.php`

```php
<?php

namespace AiZippy\Shop;

use AiZippy\Core\ViteAssets;

class MostOrderedAssets {
    public static function register() {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue() {
        // Only enqueue on pages with the block
        if (!has_block('ai-zippy-child/most-ordered')) {
            return;
        }

        ViteAssets::enqueue(
            'ai-zippy-most-ordered',
            [
                'type' => 'js',
                'deps' => ['wp-element', 'wp-dom-ready'],
            ]
        );
    }
}
```

### **Register ở loader.php:**

```php
// src/wp-content/themes/ai-zippy/inc/loader.php

$modules = [
    // existing...
    \AiZippy\Api\MostOrderedApi::class,
    \AiZippy\Shop\MostOrderedAssets::class,  // Add this
];

foreach ($modules as $module) {
    if (method_exists($module, 'register')) {
        $module::register();
    }
}
```

---

## 6️⃣ Testing & Debugging

### **Dev Server**
```bash
npm run dev
```

Vite sẽ watch file changes + rebuild manifest.

### **Check Manifest**
```bash
grep "most-ordered" src/wp-content/themes/ai-zippy/assets/dist/manifest.json
```

Output nên có entry như:
```json
"src/wp-content/themes/ai-zippy/src/js/most-ordered/index": {
  "file": "js/most-ordered.js",
  "css": ["css/most-ordered.css"]
}
```

### **Browser DevTools**
1. Open DevTools → Console
2. Xem console logs từ React component (📦, 📡, ✅, ❌ markers)
3. Check Network tab → `/wp-json/ai-zippy/v1/...` API calls
4. Check Elements tab → tìm container `#ai-zippy-most-ordered`

### **Common Issues**

| Problem | Solution |
|---------|----------|
| Script not loading | Dev server not running, run `npm run dev` |
| React not mounting | Check container ID matches `getElementById()` |
| API 404 error | Check endpoint URL + REST route registration |
| Manifest missing entry | Delete `assets/dist` folder + rebuild |
| Block not appearing | Check block name in `block.json` + wp-scripts build |

---

## 📁 Full Folder Structure Reference

```
src/wp-content/themes/
├── ai-zippy/                          # Parent theme
│   ├── inc/
│   │   ├── Api/
│   │   │   ├── ProductFilterApi.php
│   │   │   └── MostOrderedApi.php              ← API endpoints
│   │   ├── Shop/
│   │   │   └── MostOrderedAssets.php           ← Asset enqueueing
│   │   └── loader.php                        ← Register modules
│   │
│   └── src/js/
│       └── most-ordered/                      ← React app
│           ├── index.jsx                      ← Mounting
│           ├── MostOrdered.jsx                ← Main component
│           ├── api.js                         ← API calls
│           └── style.scss                     ← Styles
│
└── ai-zippy-child/                           # Child theme
    └── blocks/
        └── most-ordered/                      ← Gutenberg block
            ├── block.json
            ├── index.js
            ├── edit.js
            ├── save.js
            ├── render.php                     ← SSR output
            ├── style.scss
            └── editor.scss
```

---

## ✅ Checklist: Thêm React Feature Mới

- [ ] Tạo API class + endpoints (`inc/Api/`)
- [ ] Tạo React app folder (`src/js/{name}/`)
  - [ ] `index.jsx` - Mounting
  - [ ] `YourComponent.jsx` - Main component
  - [ ] `api.js` - Fetch functions
  - [ ] `style.scss` - Styles
- [ ] Add Vite entry point (`vite.config.js`)
- [ ] Tạo Gutenberg block (`blocks/{name}/`)
  - [ ] `block.json`
  - [ ] `index.js`, `edit.js`, `save.js`, `render.php`
- [ ] Tạo Assets class (`inc/Shop/`)
- [ ] Register module ở `loader.php`
- [ ] Start dev server: `npm run dev`
- [ ] Verify manifest: `grep {name} src/wp-content/themes/ai-zippy/assets/dist/manifest.json`
- [ ] Test in browser + DevTools console

---

## 🔄 Workflow Example: Adding "User Reviews" Feature

### Step 1: API (`inc/Api/UserReviewsApi.php`)
```php
register_rest_route('ai-zippy/v1', '/reviews', [
    'callback' => 'getUserReviews'
]);
```

### Step 2: React App (`src/js/user-reviews/`)
- Create `index.jsx` with mounting
- Create `UserReviews.jsx` component
- Create `api.js` with `fetchReviews()`
- Create `style.scss`

### Step 3: Vite (`vite.config.js`)
```js
'src/wp-content/themes/ai-zippy/src/js/user-reviews/index': './...'
```

### Step 4: Block (`blocks/user-reviews/`)
- Register with `registerBlockType`
- Create `render.php` container

### Step 5: Assets (`inc/UserReviewsAssets.php`)
- Enqueue React app on pages with block

### Step 6: Register (`loader.php`)
```php
\AiZippy\Api\UserReviewsApi::class,
\AiZippy\UserReviews\UserReviewsAssets::class,
```

### Step 7: Test
```bash
npm run dev
# Check browser
# Verify manifest + API calls
```

---

## 🚀 Performance Notes

- **Lazy Load:** Enqueue only on pages with the block using `has_block()`
- **Manifest:** Vite generates manifest for asset URLs (cache-busting)
- **WC Nonce:** Provided by `ViteAssets::enqueueTheme()` as `wcBlocksMiddlewareConfig`
- **CSS:** Separate SCSS files per feature, combined at build time

