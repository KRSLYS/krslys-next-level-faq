=== Next Level FAQ ===
Contributors:      krslys
Tags:              faq, accordion, questions, answers, gutenberg
Requires at least: 5.8
Tested up to:      6.9
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Flexible FAQ plugin with customizable styling, live preview, and Gutenberg block support.

== Description ==

**Next Level FAQ** is a lightweight, professional FAQ plugin for WordPress with a focus on design freedom. Organize questions into FAQ Groups, configure colors, layouts, and animations from a dedicated admin interface, then embed them anywhere using a shortcode or the native Gutenberg block.

= Key Features =

* **FAQ Groups** — organize questions into named groups, each with its own theme and display settings.
* **Live style preview** — see your changes before saving, rendered entirely in the browser.
* **Theme presets** — choose from flat, cards, bordered, clean, or striped layouts.
* **Full design control** — customize colors, spacing, borders, and animations per group.
* **Shortcode support** — use `[krslys_nlf group="ID"]` anywhere: pages, posts, or widgets.
* **Gutenberg block** — native block included for the block editor.
* **Custom database tables** — dedicated tables for performance; does not pollute `wp_posts`.
* **Clean & lightweight** — no bloat, no external dependencies on the front end.
* **RTL ready** — full right-to-left language support.
* **GPL licensed** — 100% open source.

== Installation ==

1. Upload the `krslys-next-level-faq` folder to the `/wp-content/plugins/` directory, or install it directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress. The plugin creates its database tables automatically on activation.
3. Go to **FAQs** in the WordPress admin menu to get started.

== Frequently Asked Questions ==

= How do I display an FAQ group on a page? =

Use the shortcode with the group ID:

`[krslys_nlf group="1"]`

The group ID is shown on the FAQ Groups list page and in the **How To Use** sidebar after saving a group.

= Can I have multiple FAQ groups? =

Yes. Create as many groups as you need from **FAQs → FAQ Groups**. Each group has its own questions, theme, and display settings.

= Is the plugin compatible with the block editor (Gutenberg)? =

Yes. A native Gutenberg block is included so you can insert any FAQ group directly from the block editor without using a shortcode.

= Does it support RTL languages (Arabic, Hebrew, etc.)? =

Yes. The plugin includes full RTL stylesheet support.

= Where are the settings stored? =

FAQ groups and their style settings are stored in dedicated custom database tables (`wp_nlf_faq_groups`, `wp_nlf_faq_items`, `wp_nlf_plugin_settings`). The plugin does not use `wp_posts` or `wp_postmeta`.

= Will the plugin slow down my site? =

No. Frontend assets are only loaded on pages where a FAQ shortcode or block is present. The CSS is pre-generated and served as a static file.

== Screenshots ==

1. FAQ Groups dashboard — overview of all groups with quick actions.
2. Group editor — Content tab for managing questions and answers.
3. Group editor — Appearance tab with live preview.
4. FAQ displayed on the front end with custom styling.
5. Gutenberg block inserter.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade needed.
