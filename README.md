## Next Level FAQ

A lightweight, professional FAQ plugin for WordPress with a focus on design. Configure colors, typography, spacing, and animation from a dedicated style page, then drop a shortcode into any page to display a styled FAQ.

### Installation

- Upload the `next-level-faq` folder to the `wp-content/plugins` directory.
- Activate the plugin through the "Plugins" page in WordPress.
- Go to `Settings → Next Level FAQ` to configure your FAQ styles.

### Usage

1. Configure your global FAQ style in the admin style page (`Settings → Next Level FAQ`).
2. Create your FAQ entries under `FAQs` in the WordPress admin:
   - Each FAQ is a post of type `FAQ`.
   - Use the **FAQ Content** metabox to enter the **Question** and **Answer**. The post title is kept in sync with the question for clarity.
3. Add the shortcode `[krslys_nlf]` inside any post, page, or widget area to render all published FAQs using your saved styles.

### Styling

- The plugin stores your style choices in a WordPress option and compiles them into a generated CSS file.
- That CSS file is automatically enqueued on the front-end (and can be reused by a future Gutenberg block implementation).

### Gutenberg (roadmap)

- A basic `block.json` is included for a `next-level-faq/faq` block so that a future editor script can reuse the same styling.
- The generated CSS is registered as the block's `style` handle so FAQs look consistent across front-end and editor.


