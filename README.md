# Next Level FAQ & Accordion

A lightweight, professional FAQ and Accordion plugin for WordPress with customizable styling, schema markup, and Gutenberg block support.

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.7 / MariaDB 10.3 or higher

---

## Installation

1. Clone or copy the `krslys-next-level-faq-accordion` folder into `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress admin.
3. On activation the plugin creates its custom database tables automatically.
4. Navigate to **FAQs** in the admin menu to get started.

---

## Features

- **FAQ Groups** — organize questions into named groups with their own theme and display settings.
- **Accordion Groups** — collapsible content sections with the same powerful styling options.
- **FAQPage Schema Markup** — automatic JSON-LD structured data for Google rich results.
- **Live Style Preview** — see your changes before saving, rendered entirely in the browser.
- **Theme Presets** — 5 built-in presets: flat, cards, bordered, clean, and striped.
- **Full Design Control** — customize colors, spacing, borders, and animations per group.
- **Gutenberg Blocks** — native blocks for both FAQ and Accordion in the block editor.
- **Shortcode Support** — use `[krslys_nlfa group="ID"]` anywhere.
- **Accessibility (WCAG 2.1 AA)** — semantic `<button>` elements, ARIA attributes, keyboard navigation.
- **RTL Ready** — automatic right-to-left language support via `dir="auto"`.
- **Import / Export** — backup and migrate FAQ and Accordion groups as JSON.
- **Custom Capability** — `manage_krslys_nlfa` for delegating FAQ management without full admin access.
- **Conditional Asset Loading** — CSS/JS only load on pages with FAQ or Accordion content.

---

## Architecture

The plugin uses dedicated custom tables — it does **not** use `wp_posts` or `wp_postmeta`.

| Table | Purpose |
|---|---|
| `{prefix}krslys_nlfa_groups` | FAQ/Accordion groups with JSON theme and display settings |
| `{prefix}krslys_nlfa_items` | Individual questions and answers, linked to groups |
| `{prefix}krslys_nlfa_settings` | Plugin settings stored as JSON key-value pairs |

Classes follow PSR-4 autoloading under the `Krslys\NextLevelFaqAccordion` namespace.
Each subsystem (Database, Admin, Frontend, Blocks, Styles) registers its own WordPress
hooks through a static `init()` method.

---

## Usage

### Creating Groups

1. Go to **FAQs → FAQ Groups** (or **Accordion Groups**) in the WordPress admin.
2. Click **Add New**.
3. In the **Content** tab, add questions and answers (or titles and content for accordions).
4. In the **Appearance** tab, choose a theme preset or configure custom colors.
5. Click **Save**.

### Embedding a Group

**Shortcode:**
```
[krslys_nlfa group="1"]
```

**PHP template:**
```php
echo do_shortcode( '[krslys_nlfa group="1"]' );
```

**Gutenberg:** Use the "Next Level FAQ" or "Next Level Accordion" blocks.

The group ID is shown on the groups list page and in the **How To Use** sidebar after saving.

---

## Admin Interface

```
FAQs  (Dashboard)
  ├── Dashboard          — Overview, stats, settings, upcoming features
  ├── FAQ Groups         — Create, edit, and manage FAQ groups
  ├── Accordion Groups   — Create, edit, and manage accordion sections
  └── Tools              — Import / export data
```

---

## Theme Presets

| Slug | Description |
|---|---|
| `minimal` | Clean flat layout, plus/minus icons |
| `modern` | Elevated card style |
| `card` | Bordered card layout |
| `outline` | Left-border accent |
| `contrast` | High contrast with striped rows |

---

## Development

### Build

```bash
npm install
npm run build
```

### Tests

```bash
# PHP unit tests
composer install
vendor/bin/phpunit

# E2E tests (requires WordPress + WP-CLI)
npx playwright install chromium
npx playwright test
```

### Coding Standards

- PHP: WordPress ruleset via `phpcs`
- JS: WordPress JS coding standards
- All AJAX operations go through `wp_ajax_nlf_save_faq_group_ajax`
- Live preview in the group editor is rendered client-side — no AJAX round-trip

---

## License

GPL-2.0-or-later
