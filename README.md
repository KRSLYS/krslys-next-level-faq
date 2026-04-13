# Next Level FAQ

A lightweight, professional FAQ plugin for WordPress with a focus on design flexibility.
Create and manage FAQ groups, configure styles and layouts from a dedicated admin interface,
and embed them anywhere using a shortcode or Gutenberg block.

---

## Development Status

> **Pre-release — not yet publicly available.**
>
> This plugin has not been published to WordPress.org or distributed in any other form.
> There are no active installations and no production user data.
>
> **What this means for development:**
>
> - No backward compatibility constraints apply.
> - Database schema, option names, hook names, and public APIs can be changed freely.
> - Deprecation notices, migration helpers, and legacy shims are not required.
> - Breaking changes can be made without a major version bump.
> - All architectural decisions should be made on merit alone — never to preserve
>   compatibility with code that was never shipped.
>
> This notice will be removed when a stable public version is released.

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.7 / MariaDB 10.3 or higher

---

## Installation (Development)

1. Clone or copy the `krslys-next-level-faq` folder into `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress admin.
3. On activation the plugin creates its custom database tables automatically.
4. Navigate to **FAQs** in the admin menu to get started.

---

## Architecture

The plugin uses dedicated custom tables — it does **not** use `wp_posts` or `wp_postmeta`.

| Table | Purpose |
|---|---|
| `wp_krslys_nlfa_groups` | FAQ groups with JSON theme and display settings |
| `wp_krslys_nlfa_items` | Individual questions and answers, linked to groups |
| `wp_krslys_nlfa_settings` | Global plugin configuration stored as JSON |

Classes follow PSR-4 autoloading under the `Krslys\NextLevelFaq` namespace.
Each subsystem (Database, Admin, Frontend, Blocks, Styles) registers its own WordPress
hooks through a static `init()` method — the main plugin class only boots subsystems.

---

## Usage

### Creating FAQ Groups

1. Go to **FAQs → FAQ Groups** in the WordPress admin.
2. Click **Add New FAQ Group**.
3. In the **Content** tab, add questions and answers.
4. In the **Appearance** tab, choose a theme preset or configure custom colors.
5. Click **Save**.

### Embedding a Group

Use the shortcode with the group ID:

```
[krslys_nlfa group="1"]
```

Or in PHP templates:

```php
echo do_shortcode( '[krslys_nlfa group="1"]' );
```

The group ID is shown on the FAQ Groups list page and in the **How To Use** sidebar
after saving a group.

---

## Styling System

- Each FAQ group stores its own theme settings (preset + optional color overrides) as JSON.
- The `Style_Generator` class compiles these settings into CSS custom properties at render time.
- A separate `NLF_FAQ_CSS_VERSION` constant controls CSS asset cache-busting independently
  of the plugin version — CSS caches are only invalidated when styles actually change.

### Available Theme Presets

| Slug | Description |
|---|---|
| `default` | Clean flat layout, plus/minus icons |
| `cards` | Elevated card style |
| `bordered` | Left-border accent |
| `clean` | Minimal, no borders |
| `striped` | Alternating row backgrounds |

---

## Admin Interface

```
FAQs  (Dashboard)
  ├── Dashboard        — Overview with links to main sections
  ├── FAQ Groups       — Create, edit, and manage FAQ groups
  ├── Style & Layout   — Global style defaults and presets
  └── Tools            — Import / export
```

---

## Gutenberg Block

A `block.json` block (`next-level-faq/faq-group`) is registered for embedding groups
in the block editor. The same CSS custom properties used by the shortcode apply to
the block, ensuring visual consistency.

---

## Development Notes

- Run `npm install && npm run build` to compile block assets.
- PHP coding standards: WordPress ruleset via `phpcs`.
- All AJAX save operations go through `wp_ajax_nlf_save_faq_group_ajax`.
- The live preview in the group editor is rendered entirely client-side from the current
  form state — no AJAX round-trip is made for preview updates.
