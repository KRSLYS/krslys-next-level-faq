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

**Next Level FAQ** is a lightweight, professional FAQ plugin for WordPress with a focus on design freedom. Configure colors, typography, spacing, and animation from a dedicated style page, then drop a shortcode into any page to display a beautifully styled FAQ section.

= Key Features =

* **Live style preview** — see your changes before saving.
* **Full design control** — customize colors, fonts, spacing, borders, and animations.
* **Shortcode support** — use `[krslys_nlf]` anywhere: pages, posts, or widgets.
* **Gutenberg block** — native block included for the block editor.
* **FAQ Groups** — organize questions into groups for better management.
* **Clean & lightweight** — no bloat, no external dependencies on the front end.
* **RTL ready** — full right-to-left language support.
* **GPL licensed** — 100% open source.

== Installation ==

1. Upload the `krslys-next-level-faq` folder to the `/wp-content/plugins/` directory, or install it directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Settings → Next Level FAQ** to configure your FAQ styles.

== Frequently Asked Questions ==

= How do I display the FAQ on a page? =

Add the shortcode `[krslys_nlf]` to any post, page, or widget area.

= Can I group my FAQ questions? =

Yes. You can organize questions into FAQ Groups from the WordPress admin and display a specific group using `[krslys_nlf group="group-slug"]`.

= Is the plugin compatible with the block editor (Gutenberg)? =

Yes. A native Gutenberg block is included so you can insert the FAQ directly from the block editor without using a shortcode.

= Does it support RTL languages (Arabic, Hebrew, etc.)? =

Yes. The plugin includes full RTL stylesheet support.

= Where are the styles stored? =

Your style choices are stored as a WordPress option and compiled into a generated CSS file that is automatically enqueued on the front end.

== Screenshots ==

1. FAQ style settings page with live preview.
2. FAQ displayed on the front end with custom styling.
3. Gutenberg block inserter.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade needed.
