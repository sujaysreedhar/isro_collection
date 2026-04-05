# Collection Portal — Developer Guide

> **Version 2.0** — Covers the Module System, Hook Registry, and Theme Engine.

This guide provides everything a developer needs to create custom **Modules** and **Themes** for the Collection Portal. It is divided into two main sections:

1. [Creating a Module](#part-1-creating-a-module)
2. [Creating a Theme](#part-2-creating-a-theme)

---

## Architecture Overview

The portal is built on three pillars that enable extensibility:

| Component | File | Purpose |
|---|---|---|
| **HookRegistry** | `includes/HookRegistry.php` | WordPress-style event system with Actions and Filters |
| **ModuleManager** | `includes/ModuleManager.php` | Discovers, loads, and boots modules from `modules/` |
| **ThemeManager** | `includes/ThemeManager.php` | Resolves template paths with active-theme → default fallback |

Supporting classes:

| Component | File | Purpose |
|---|---|---|
| **BaseModule** | `includes/BaseModule.php` | Abstract base class all modules must extend |
| **ModuleDB** | `includes/ModuleDB.php` | Safe table creation/deletion for modules |

---

# Part 1: Creating a Module

## 1.1 Module Directory Structure

Every module lives inside `modules/{your_module_slug}/`:

```
modules/
└── my_custom_module/
    ├── module.json          # Metadata (REQUIRED, or use header comments in module.php)
    ├── module.php           # Entry point (REQUIRED)
    ├── admin_settings.php   # Optional: admin page views
    ├── assets/              # Optional: CSS, JS, images
    │   ├── style.css
    │   └── script.js
    └── templates/           # Optional: HTML fragments
        └── widget.php
```

> **Naming Convention:** The folder name is your module's **slug**. Use `snake_case` with only alphanumeric characters and underscores (e.g., `trade_manager`, `postmark_atlas`).

---

## 1.2 Module Metadata — `module.json`

The preferred way to declare metadata. The `ModuleManager` reads this file when discovering modules.

```json
{
    "name": "My Custom Module",
    "description": "A brief description of what this module does.",
    "version": "1.0",
    "author": "Your Name",
    "admin_menu_priority": 50
}
```

| Key | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Human-readable display name |
| `description` | string | Yes | Short description shown in Admin → Modules |
| `version` | string | Yes | Semantic version (e.g., `1.0`, `2.1.3`) |
| `author` | string | No | Author name (defaults to `"Unknown"`) |
| `admin_menu_priority` | int | No | Sort order in admin sidebar (lower = higher, default `100`) |

**Legacy Alternative:** If `module.json` doesn't exist, the system reads PHP header comments from `module.php`:

```php
<?php
/*
Module Name: My Custom Module
Description: A brief description.
Version: 1.0
Author: Your Name
*/
```

---

## 1.3 The Module Class — `module.php`

Your `module.php` **must** define a class that extends `BaseModule`. The class naming convention is `{PascalCaseSlug}Module`:

| Slug | Expected Class Name |
|---|---|
| `my_custom_module` | `MyCustomModuleModule` |
| `trade_manager` | `TradeManagerModule` |
| `postmark_atlas` | `PostmarkAtlasModule` |

### Base Class API

```php
class BaseModule {
    protected $pdo;       // PDO database connection
    protected $slug;      // Module slug string
    protected $metadata;  // Array from module.json

    // Called on every page load when module is active.
    // Default implementation auto-dispatches to optional named sub-methods
    // (see Section 1.3a). Override entirely for simple modules.
    public function boot();

    // OPTIONAL — Called once when module is first enabled via Admin panel.
    public function activate() {}

    // OPTIONAL — Called once when module is disabled via Admin panel.
    public function deactivate() {}

    public function getSlug(): string;
    public function getMetadata(): array;
}
```

### 1.3a — Optional Sub-method Boot Pattern

For complex modules, instead of putting everything in `boot()`, define any of these named methods — `boot()` calls them automatically:

| Method | Purpose |
|---|---|
| `registerRoutes()` | Hook into `route_request` for frontend URLs |
| `registerAdminMenu()` | Register `admin_menu` and `admin_page_*` hooks |
| `registerSearch()` | Hook into `search_results` filter |
| `registerHooks()` | All other actions / filters |

```php
class MyComplexModule extends BaseModule {
    // boot() is NOT overridden — BaseModule auto-calls the methods below

    protected function registerRoutes() {
        HookRegistry::addFilter('route_request', function($handled, $uri) {
            if ($uri === 'my-page') {
                require __DIR__ . '/my_page.php';
                return true;
            }
            return $handled;
        }, 10, 2);
    }

    protected function registerAdminMenu() {
        HookRegistry::addAction('admin_menu', function() {
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '" class="sidebar-link text-slate-300">My Module</a>';
        });
        HookRegistry::addAction('admin_page_' . $this->slug, function() {
            require_once __DIR__ . '/admin_page.php';
        });
    }

    protected function registerSearch() {
        $pdo = $this->pdo; // capture — never use global $pdo in closures
        HookRegistry::addFilter('search_results', function($results, $params) use ($pdo) {
            // ... search logic
            return $results;
        }, 10, 2);
    }
}
```

> **Rule:** Never use `global $pdo` inside hook closures. The PDO instance is already available as `$this->pdo` — capture it with `use` before registering the closure.

### Minimal Example

```php
<?php
// modules/my_custom_module/module.php

class MyCustomModuleModule extends BaseModule {

    public function boot() {
        // Simple module — override boot() directly (sub-methods are optional)
        HookRegistry::addAction('frontend_footer', function() {
            echo '<p style="text-align:center; color:#888; font-size:12px;">Powered by My Custom Module</p>';
        });
    }

    public function activate() {
        // Create a database table when module is enabled
        ModuleDB::createTable($this->pdo, 'my_module_data', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");
    }

    public function deactivate() {
        // Optionally clean up the table
        // ModuleDB::dropTable($this->pdo, 'my_module_data');
    }
}
```

---

## 1.4 Database Access — `ModuleDB`

Modules should **never** write raw `CREATE TABLE` SQL directly. Use `ModuleDB` for safe, validated table operations.

### `ModuleDB::createTable(PDO $pdo, string $tableName, string $schemaDef): bool`

Creates a table if it doesn't exist. Returns `true` on success.

**Constraints:**
- Table names must be alphanumeric + underscores only (`^[a-zA-Z0-9_]+$`).
- Core table names are **protected** and cannot be used: `items`, `categories`, `media`, `settings`, `admins`, `tags`, `narratives`.

```php
ModuleDB::createTable($this->pdo, 'trade_offers', "
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_email VARCHAR(255),
    offer_details TEXT,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
");
```

### `ModuleDB::dropTable(PDO $pdo, string $tableName): bool`

Drops a table. Use sparingly — typically only during deactivation if the module data is truly disposable.

```php
ModuleDB::dropTable($this->pdo, 'trade_offers');
```

---

## 1.5 The Hook System — `HookRegistry`

The hook system is the primary extension point. It has two types: **Actions** (do something) and **Filters** (transform data).

### Actions

Actions let you **execute code** at specific points in the application lifecycle.

```php
// Register an action
HookRegistry::addAction(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1);

// The system triggers it at the appropriate time
HookRegistry::doAction(string $tag, ...$args);
```

**Parameters:**
- `$tag` — The hook name (see table below).
- `$callback` — A callable (closure, function name, or `[$object, 'method']`).
- `$priority` — Lower numbers run first. Default: `10`.
- `$accepted_args` — Number of arguments your callback accepts.

### Filters

Filters let you **modify data** as it passes through the system.

```php
// Register a filter
HookRegistry::addFilter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1);

// The system applies it             
$value = HookRegistry::applyFilters(string $tag, $value, ...$args);
```

Your filter callback **must return** the (modified) value:

```php
HookRegistry::addFilter('frontend_nav_links', function($links) {
    $links['my_page'] = ['url' => SITE_URL . '/my-page.php', 'label' => 'My Page'];
    return $links; // MUST return
});
```

---

## 1.6 Available Hooks Reference

### Admin Hooks

| Hook Name | Type | Location | Arguments | Description |
|---|---|---|---|---|
| `admin_head` | Action | `admin/layout.php` `<head>` | — | Inject CSS, meta tags, or JS into the admin `<head>` |
| `admin_menu` | Action | `admin/layout.php` sidebar | — | Add navigation links to the admin sidebar (both desktop and mobile) |
| `admin_footer` | Action | `admin/layout.php` before `</body>` | — | Inject scripts or HTML at the bottom of admin pages |
| `admin_page_{slug}` | Action | `admin/module_page.php` | — | Render the content area of a module's custom admin page. Replace `{slug}` with your module slug |

> **Note:** `admin_menu_mobile` is deprecated. The mobile drawer now uses the same `admin_menu` hook automatically.

### Admin Sidebar Sections — `admin_sidebar_links` Filter

The admin sidebar is **data-driven**. Use the `admin_sidebar_links` filter to add links to any existing section, or add a brand-new section:

```php
HookRegistry::addFilter('admin_sidebar_links', function(array $sections) {
    // Add a link to an existing section:
    $sections['catalog']['links']['my_report'] = [
        'url'   => SITE_URL . '/admin/module_page.php?m=my_module',
        'label' => 'My Report',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="..."/>',
    ];

    // Or add a completely new section:
    $sections['my_module'] = [
        'label' => 'My Module',
        'links' => [
            'settings' => [
                'url'   => SITE_URL . '/admin/module_page.php?m=my_module',
                'label' => 'Settings',
                'icon'  => '<path .../>', // Optional SVG path content
            ],
        ],
    ];

    return $sections; // MUST return
});
```

Existing section keys: `overview`, `catalog`, `content`, `system`.

> The old `admin_menu` hook (raw HTML strings) still works for backward compatibility. Prefer `admin_sidebar_links` for new modules.

### Frontend Hooks

| Hook Name | Type | Location | Arguments | Description |
|---|---|---|---|---|
| `frontend_head` | Action | Theme `header.php` `<head>` | — | Inject CSS, meta tags, or JS into the frontend `<head>` |
| `frontend_header` | Action | Theme `header.php` after `<header>` | — | Inject banners, announcements, or widgets after the header |
| `frontend_footer` | Action | Theme `footer.php` before `</body>` | — | Inject scripts or HTML at the bottom of frontend pages |
| `frontend_home` | Action | Theme `index.php` | — | Inject content sections on the homepage |
| `frontend_item_actions` | Action | Theme `item_detail.php` | `$item` (array) | Add action buttons (share, download, etc.) on item detail |
| `item_before_content` | Action | Theme `item_detail.php` | `$item` (array) | Inject content before the main item detail content |
| `item_after_content` | Action | Theme `item_detail.php` | `$item` (array) | Inject content after the main item detail content |

### Filter Hooks

| Hook Name | Type | Location | Arguments | Description |
|---|---|---|---|---|
| `frontend_nav_links` | Filter | `includes/frontend.php` | `$links` (array) | Modify the frontend navigation links array |
| `admin_sidebar_links` | Filter | `admin/layout.php` | `$sections` (array) | Modify or extend the admin sidebar sections/links |
| `route_request` | Filter | `includes/Router.php` | `$handled` (bool), `$uri` (string) | Handle a URL and return `true` to stop further routing |
| `search_results` | Filter | `includes/SearchEngine.php` | `$results` (array), `$params` (array) | Append or modify search results (e.g., add module content) |

### Module Lifecycle Hooks

| Hook Name | Type | Triggered When | Arguments |
|---|---|---|---|
| `activate_{slug}` | Action | Module is enabled in Admin → Modules | — |
| `deactivate_{slug}` | Action | Module is disabled in Admin → Modules | — |

---

## 1.7 Creating Custom Admin Pages

Modules can register their own admin pages using the `admin_menu` + `admin_page_{slug}` pattern:

```php
public function boot() {
    // Step 1: Add a link to the sidebar
    HookRegistry::addAction('admin_menu', function() {
        echo '<div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">My Module</div>';
        echo '<a href="' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '"
              class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 hover:text-white font-medium transition-colors">
              Settings</a>';
    });

    // Also add to mobile nav!
    HookRegistry::addAction('admin_menu_mobile', function() {
        echo '<a href="' . SITE_URL . '/admin/module_page.php?m=' . $this->slug . '"
              class="block px-3 py-2 rounded-md hover:bg-gray-800 text-gray-300">
              My Module Settings</a>';
    });

    // Step 2: Render the page content
    HookRegistry::addAction('admin_page_' . $this->slug, function() {
        echo '<h2 class="text-xl font-semibold mb-4">My Module Settings</h2>';
        // Render forms, tables, etc.
    });
}
```

For complex pages, require external files:

```php
HookRegistry::addAction('admin_page_' . $this->slug, function() {
    $page = $_GET['page'] ?? 'default';
    
    if ($page === 'settings') {
        require_once __DIR__ . '/admin_settings.php';
    } elseif ($page === 'reports') {
        require_once __DIR__ . '/admin_reports.php';
    }
});
```

These pages are accessed at: `admin/module_page.php?m={slug}&page={subpage}`

---

## 1.8 Adding Frontend Navigation

Use the `frontend_nav_links` filter to inject links into the site navigation:

```php
HookRegistry::addFilter('frontend_nav_links', function($links) {
    $links['my_page'] = [
        'url'   => SITE_URL . '/my-page.php',
        'label' => 'My Page'
    ];
    return $links;
});
```

The key (`'my_page'`) is used to highlight the active menu item.

---

## 1.9 Full Module Example — `hello_world`

```
modules/
└── hello_world/
    ├── module.json
    └── module.php
```

**module.json:**
```json
{
    "name": "Hello World",
    "description": "Adds a greeting banner to the homepage and a custom admin page.",
    "version": "1.0",
    "author": "Developer",
    "admin_menu_priority": 99
}
```

**module.php:**
```php
<?php

class HelloWorldModule extends BaseModule {

    public function boot() {
        // Add a banner on the homepage
        HookRegistry::addAction('frontend_home', function() {
            echo '<div style="text-align:center; padding:20px; background:#dbeafe; 
                  border-radius:12px; margin:20px auto; max-width:600px;">
                  <h2 style="font-size:24px; font-weight:bold; color:#1e40af;">Hello, World!</h2>
                  <p style="color:#3b82f6;">This message is injected by the Hello World module.</p>
                  </div>';
        });

        // Add an admin page
        HookRegistry::addAction('admin_menu', function() {
            echo '<a href="' . SITE_URL . '/admin/module_page.php?m=hello_world" 
                  class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-800 
                  hover:text-white font-medium transition-colors">Hello World</a>';
        });

        HookRegistry::addAction('admin_page_hello_world', function() {
            echo '<h2 class="text-xl font-bold mb-4">Hello World Settings</h2>';
            echo '<p class="text-gray-600">Nothing to configure — this module just works!</p>';
        });

        // Inject custom CSS
        HookRegistry::addAction('frontend_head', function() {
            echo '<style>.hello-badge { background: #2563eb; color: white; 
                  padding:4px 12px; border-radius:999px; font-size:0.8rem; }</style>';
        });

        // Add a badge on every item detail page
        HookRegistry::addAction('item_after_content', function($item) {
            echo '<div class="mt-4 text-center">
                  <span class="hello-badge">👋 Viewed via Hello World Module</span>
                  </div>';
        }, 10, 1);

        // Add a nav link
        HookRegistry::addFilter('frontend_nav_links', function($links) {
            $links['hello'] = ['url' => '#', 'label' => '👋 Hello'];
            return $links;
        });
    }
}
```

After creating these files, go to **Admin → Modules** and click **Enable**. Done!

---

# Part 2: Creating a Theme

## 2.1 How the Theme Engine Works

The `ThemeManager` class resolves template file paths using a two-level lookup:

```
1. themes/{active_theme}/{template}.php   ← Checked first
2. themes/default/{template}.php          ← Fallback
```

This means you only need to override the templates you want to change. Missing templates automatically fall back to the `default` theme.

```
ThemeManager::getTemplatePath('index.php')
    → Is themes/modern_blue/index.php present?
        → YES: Return that path
        → NO:  Return themes/default/index.php
```

The active theme slug is stored in the `settings` database table under the key `active_theme` and defaults to `"default"`.

---

## 2.2 Theme Directory Structure

```
themes/
├── default/              ← The built-in fallback theme (DO NOT DELETE)
│   ├── header.php
│   ├── footer.php
│   ├── index.php
│   ├── search.php
│   ├── gallery.php
│   ├── item_detail.php
│   └── atlas.php
│
└── my_theme/             ← Your custom theme
    ├── header.php        ← Override global header
    ├── footer.php        ← Override global footer
    ├── index.php         ← Override homepage
    ├── search.php        ← Override search/catalog
    ├── gallery.php       ← Override media gallery
    ├── item_detail.php   ← Override item detail page
    └── atlas.php         ← Override atlas/map page
```

> **You don't need ALL files.** If you only want to change the header and footer, create just `header.php` and `footer.php` in your theme folder. All other templates will automatically fall back to `default`.

---

## 2.3 Template Files Reference

| Template | Controller | Purpose | Key Variables Available |
|---|---|---|---|
| `header.php` | All pages | `<!DOCTYPE>`, `<head>`, navigation, opening `<body>` | `$pageTitle`, `$currentMenu`, `$additionalHead` |
| `footer.php` | All pages | Footer content, closing `</body></html>` | — |
| `index.php` | `index.php` | Homepage with featured items | `$featuredItems`, `$storage` |
| `search.php` | `search.php` | Search results with faceted filters | `$items`, `$q`, `$totalResults`, `$totalPages`, `$page`, `$facets`, `$selectedCategories`, `$selectedTags`, `$catNameMap`, `$tagNameMap`, `$storage` |
| `gallery.php` | `gallery.php` | Masonry media gallery | `$mediaItems`, `$storage` |
| `item_detail.php` | `item_detail.php` | Single item detail view | `$item`, `$allMedia`, `$relatedStories`, `$tags`, `$ogUrl`, `$ogImage`, `$jsonLdJson`, `$storage` |
| `atlas.php` | `atlas.php` | Interactive map view | `$jsonLocations` |

---

## 2.4 Anatomy of a Theme Template

Every template follows the same pattern:

```php
<?php
// 1. Set page-level variables (used by header.php)
$pageTitle = 'My Page - ' . SITE_TITLE;
$currentMenu = 'home';  // Highlights the active nav item

// 2. Optionally buffer additional <head> content
ob_start();
?>
<link rel="stylesheet" href="path/to/extra.css">
<style>/* Custom page-specific styles */</style>
<?php
$additionalHead = ob_get_clean();

// 3. Include the header (uses ThemeManager for fallback)
require_once ThemeManager::getHeader();
?>

<!-- 4. Your HTML content here -->
<main>
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <!-- Use the variables passed from the controller -->
</main>

<!-- 5. Fire any relevant hooks -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_home'); } ?>

<?php 
// 6. Include the footer
require_once ThemeManager::getFooter(); 
?>
```

### Important Conventions

| Convention | Details |
|---|---|
| **`$pageTitle`** | Set before including `header.php`. Used in `<title>` tag |
| **`$currentMenu`** | String key matching a nav link (e.g., `'home'`, `'search'`, `'gallery'`, `'atlas'`). Highlights the active nav item |
| **`$additionalHead`** | Buffer extra CSS/JS to inject into `<head>` before including header |
| **`ThemeManager::getHeader()`** | Always use this (not a hardcoded path). Enables theme fallback |
| **`ThemeManager::getFooter()`** | Same as above for footer |
| **Hook calls** | Always wrap in `if (class_exists('HookRegistry'))` to prevent errors if the system isn't loaded |

---

## 2.5 The Header Template

Your `header.php` should output the entire top section of the HTML document:

```php
<?php
global $pageTitle, $additionalHead, $currentMenu;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? SITE_TITLE) ?></title>
    
    <!-- Tailwind CSS (or your own framework) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Your theme's custom styles -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=YourFont&display=swap');
        body { font-family: 'YourFont', sans-serif; }
        /* ... theme-specific styles ... */
    </style>
    
    <!-- Additional head content from individual pages -->
    <?= $additionalHead ?? '' ?>
    
    <!-- Module hook: allows modules to inject CSS/JS -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>
</head>
<body>
    <header>
        <!-- Your navigation bar -->
        <nav>
            <a href="<?= SITE_URL ?>"><?= SITE_TITLE ?></a>
            <?php renderFrontendNav($currentMenu ?? ''); ?>
        </nav>
    </header>
    
    <!-- Module hook: allows modules to inject banners/widgets -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); } ?>
```

> **Key:** Always call `renderFrontendNav($currentMenu)` for the navigation. This function uses the `frontend_nav_links` filter, allowing modules to dynamically add/remove menu items.

---

## 2.6 The Footer Template

Your `footer.php` should close the HTML document:

```php
<?php // themes/my_theme/footer.php ?>
    <footer>
        <p>&copy; <?= date('Y') ?> <?= SITE_TITLE ?>. All rights reserved.</p>
    </footer>
    
    <!-- Module hook: allows modules to inject scripts -->
    <?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_footer'); } ?>
</body>
</html>
```

---

## 2.7 Available Global Variables & Functions

These are available in all theme templates:

| Variable/Function | Type | Description |
|---|---|---|
| `SITE_URL` | `string` | Base URL of the site (e.g., `http://localhost/collection`) |
| `SITE_TITLE` | `string` | The site title from settings |
| `$appSettings` | `array` | All key-value pairs from the `settings` database table |
| `$storage` | `object` | Storage driver instance (has `->url()` method for generating file URLs) |
| `$pdo` | `PDO` | Database connection |
| `renderFrontendNav($activeKey)` | `function` | Renders the navigation bar with module-injected links |
| `ThemeManager::getHeader()` | `static` | Returns path to the header template |
| `ThemeManager::getFooter()` | `static` | Returns path to the footer template |
| `ThemeManager::getTemplatePath($name)` | `static` | Returns resolved path for any template |
| `ThemeManager::getActiveTheme()` | `static` | Returns the active theme slug string |

---

## 2.8 Working with Media URLs

When displaying images or files from the uploads directory, always use the `$storage` object for compatibility with both local and S3 storage:

```php
<?php if (isset($storage)): ?>
    <img src="<?= $storage->url('display/' . $item['primary_media_path']) ?>" alt="...">
<?php else: ?>
    <img src="<?= SITE_URL ?>/uploads/display/<?= rawurlencode($item['primary_media_path']) ?>" alt="...">
<?php endif; ?>
```

---

## 2.9 Firing Hooks in Your Theme

Always include these hook points in your templates to ensure modules can extend your theme:

```php
<!-- In header.php <head> section: -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_head'); } ?>

<!-- In header.php after <header>: -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_header'); } ?>

<!-- In index.php (homepage): -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_home'); } ?>

<!-- In item_detail.php before main content: -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('item_before_content', $item); } ?>

<!-- In item_detail.php after main content: -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('item_after_content', $item); } ?>

<!-- In footer.php before </body>: -->
<?php if (class_exists('HookRegistry')) { HookRegistry::doAction('frontend_footer'); } ?>
```

> **⚠️ If you omit these hooks, modules that inject content will silently stop working for your theme.** Always include them.

---

## 2.10 Step-by-Step: Create a New Theme

### Step 1: Create the directory

```
themes/my_awesome_theme/
```

### Step 2: Create `header.php` and `footer.php`

These define your theme's global look and feel (fonts, colors, layout, navigation). See Sections 2.5 and 2.6 for the required structure.

### Step 3: Override only the pages you want

For example, to just change the homepage, create `themes/my_awesome_theme/index.php`. All other pages (search, gallery, etc.) will automatically use the `default` theme's versions.

### Step 4: Activate via Admin

Go to **Admin → System → Themes** and click **Activate** on your new theme. The change takes effect immediately site-wide.

### Step 5: Test the fallback

Delete (or rename) one of your theme templates temporarily. Verify the site falls back to the `default` theme's version without errors.

---

## 2.11 Quick Reference: Theme Checklist

- [ ] Created `themes/{slug}/` directory
- [ ] Created `header.php` with `<!DOCTYPE>`, `<head>`, nav, and hook points
- [ ] Created `footer.php` with footer content and hook points
- [ ] Overridden desired page templates (`index.php`, `search.php`, etc.)
- [ ] Used `ThemeManager::getHeader()` and `ThemeManager::getFooter()` (not hardcoded paths)
- [ ] Included all `HookRegistry::doAction()` calls for module compatibility
- [ ] Used `renderFrontendNav($currentMenu)` for navigation
- [ ] Set `$pageTitle` and `$currentMenu` before including header
- [ ] Tested with modules enabled to verify hooks fire correctly
- [ ] Verified fallback works by removing a template file

---

## Appendix A: File Tree Overview

```
collection/
├── config/
│   └── config.php              # Database, SITE_URL, SITE_TITLE, bootstrap
├── includes/
│   ├── BaseModule.php          # Abstract module base class
│   ├── HookRegistry.php        # Actions & Filters system
│   ├── ModuleDB.php            # Safe DB table operations for modules
│   ├── ModuleManager.php       # Module discovery, loading, booting
│   ├── ThemeManager.php        # Template resolution with fallback
│   ├── frontend.php            # renderFrontendNav() and shared utilities
│   └── SafePDO.php             # Secure PDO wrapper
├── modules/
│   ├── sample_module/          # Example: header-comment style module
│   ├── postmark_atlas/         # Example: class-based module with admin pages
│   ├── trade_manager/          # Example: module with its own DB tables
│   └── user_galleries/         # Example: module with frontend features
├── themes/
│   ├── default/                # Built-in fallback theme
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── index.php
│   │   ├── search.php
│   │   ├── gallery.php
│   │   ├── item_detail.php
│   │   └── atlas.php
│   └── modern_blue/            # Example: custom "Modern Blue" theme
├── admin/
│   ├── modules.php             # Module enable/disable UI
│   ├── themes.php              # Theme activation UI
│   ├── module_page.php         # Generic module admin page renderer
│   └── layout.php              # Admin sidebar + header/footer wrappers
├── index.php                   # Homepage controller
├── search.php                  # Search/catalog controller
├── gallery.php                 # Gallery controller
├── item_detail.php             # Item detail controller
└── atlas.php                   # Atlas map controller
```

---

## Appendix B: Troubleshooting

| Problem | Solution |
|---|---|
| Module doesn't appear in admin panel | Ensure `module.json` or `module.php` (with header comments) exists in `modules/{slug}/` |
| Module class not found | Verify class name follows `{PascalCaseSlug}Module` convention |
| Theme template not loading | Check the file exists at `themes/{slug}/{template}.php`. Check `active_theme` in DB |
| Hooks not firing | Ensure you wrap hook calls in `if (class_exists('HookRegistry'))` and that hook names match exactly |
| `ModuleDB::createTable` fails | Table name must be alphanumeric + underscores. Cannot use protected core table names |
| Theme changes not visible | Clear browser cache. Verify `settings.active_theme` matches your theme folder name |

---

*Happy building! 🚀*
