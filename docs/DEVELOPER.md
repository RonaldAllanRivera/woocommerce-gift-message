# WooCommerce Gift Message – Developer Documentation

This document provides engineering notes, architecture, best practices, and a manual test plan for maintaining the WooCommerce Gift Message plugin.

## Project Structure
- `woocommerce-gift-message.php` – Plugin bootstrap, constants, activation checks, feature init.
- `includes/class-wcgm.php` – Core logic: render field, validate/sanitize, cart/order meta, admin columns.
- `assets/css/frontend.css` – Minimal styling for the frontend field.
- `assets/js/frontend.js` – jQuery-based live character counter.
- `README.md` – User-facing overview and changelog.
- `docs/DEVELOPER.md` – This file.

## Architecture & Data Flow
- Render field on product page: `woocommerce_before_add_to_cart_button` → `WCGM::render_input_field()`
- Validate input on add to cart: `woocommerce_add_to_cart_validation` → `WCGM::validate_input()`
  - Enforces max length (default 150, filterable via `wcgm_max_length`)
  - Strips newlines to keep message single-line
  - Uses nonce to mitigate CSRF
- Save to cart meta: `woocommerce_add_cart_item_data` → `WCGM::add_cart_item_data()`
- Show on Cart/Checkout: `woocommerce_get_item_data` → `WCGM::display_cart_item_data()`
- Save to order items: `woocommerce_checkout_create_order_line_item` → `WCGM::add_order_item_meta()`
  - Adds visible meta using human-readable label (for templates/emails)
  - Adds hidden meta `_wcgm_gift_message` for admin column aggregation
- Admin Orders list column:
  - Classic posts table: `manage_edit-shop_order_columns`, `manage_shop_order_posts_custom_column`
  - HPOS orders table: `woocommerce_shop_order_list_table_columns`, `woocommerce_shop_order_list_table_custom_column`

## Security Practices
- Nonces: `wp_nonce_field()` + `wp_verify_nonce()` to protect the input.
- Sanitization: `sanitize_text_field()`; newlines removed.
- Escaping: `esc_html()` when outputting user-provided values.
- Capabilities: `current_user_can('manage_woocommerce')` for admin-only UI.

## Coding Standards
- Follow WordPress Coding Standards (WPCS). Recommended local setup:
  - Install PHPCS and WPCS and run against the plugin directory.
- Internationalization (i18n):
  - Text domain: `woocommerce-gift-message`
  - Wrap strings with translation functions (`__`, `_e`, etc.)
- Prefixes: Constants and meta keys prefixed with `WCGM`/`_wcgm_` to avoid collisions.
- Hooks naming: Filters and actions follow `wcgm_*` prefix.
- Enqueue assets conditionally: Frontend assets only when `is_product()`.

## Extensibility Hooks
- Filters:
  - `wcgm_max_length` – Adjust maximum characters (default 150)
  - `wcgm_gift_message_label` – Customize field label (default “Gift Message”)
- Actions:
  - `wcgm_after_field` – Output content directly after the input field

## Extensibility – quick test (mu‑plugin snippet)
Create `wp-content/mu-plugins/wcgm-ext-test.php` with:

```php
<?php
/**
 * Plugin Name: WCGM Extensibility Test
 */
add_filter('wcgm_max_length', fn($n) => 10);
add_filter('wcgm_gift_message_label', fn($l) => 'Card Note');
add_action('wcgm_after_field', function(){
    echo '<p style="margin-top:6px;color:#2271b1;">Injected via wcgm_after_field</p>';
});
```

Visit a product page:
- Label should be “Card Note”, maxlength 10, and the injected note should appear.
- Adding >10 chars should fail validation.

## Manual Test Plan

1) Length validation
- Enter >150 chars → Add to cart
  - Expect: Error notice “Gift Message must be 150 characters or fewer.” Item not added.
- Enter exactly 150 chars
  - Expect: Item added; message shown with line item.

2) Sanitization (HTML/script/newlines)
- Input: `<script>alert(1)</script> Happy <b>Birthday</b> & Friends\nLine2`
  - Add to cart → Expect: Display as `Happy Birthday & Friends Line2` (no tags, single line, safely escaped)
- Input with leading/trailing spaces and multiple spaces
  - Expect: Newlines stripped; HTML not rendered; content escaped.

3) Nonce/security
- In DevTools, change `wcgm_gift_message_nonce` to a random string and submit
  - Expect: Error notice “Security check failed for Gift Message.” Item not added.

4) Empty input
- Leave message empty
  - Expect: Item added; no message shown/saved.

5) Cart line merge behavior
- Add same product twice with two different messages
  - Expect: Two separate cart lines (unique key prevents merge)

6) Persistence & display surfaces
- Place order and verify message appears on:
  - Cart and Checkout (line item data)
  - Thank You page
  - My Account → Orders → View (under the item)
  - Order confirmation email (order item meta)
  - Admin → Orders → Order details (item meta)
  - Admin → Orders list column (aggregated; semicolon-separated for multiple)

7) Special characters
- Input: `Happy "B-Day" & 🎉`
  - Expect: Safely displayed (quotes/ampersands escaped; emoji retained where supported)

8) Assets load only on product pages
- Visit Home/Cart/Checkout pages
  - Expect: No `wcgm-frontend` CSS/JS enqueued. On a single product page, both are enqueued.

9) JavaScript live counter
- Type in the input
  - Expect: `#wcgm-counter` increments. When length exceeds max, input gets `.wcgm-too-long` class.

10) Admin Orders column – permissions & modes
- As Shop Manager/Admin: Orders list shows the Gift Message column.
- As a user without `manage_woocommerce`: column hidden.
- If HPOS is enabled, column appears in the HPOS table; otherwise in legacy posts table.

11) Hooks behavior
- With the mu‑plugin snippet above:
  - Label becomes “Card Note” everywhere new meta is created.
  - Max length enforced at 10.
  - Content from `wcgm_after_field` appears under the input.

12) Order item meta – visible vs hidden
- In the order screen, each item should have a human‑readable meta with the label value (e.g., “Gift Message” or “Card Note”).
- A hidden meta `_wcgm_gift_message` should also exist; the Orders list column aggregates from this.

13) Emails and resends
- WooCommerce → Orders → select order → Order actions → Resend emails.
- Confirm the message appears under each line item in both customer and admin emails.

14) i18n smoke test
- Switch Site Language in Settings → General.
- Verify strings are wrapped for translation (text domain `woocommerce-gift-message`); actual translations depend on shipping a `.pot` and language files.

## Deployment Notes
- Requires WooCommerce active before activation; otherwise the plugin deactivates itself.
- Tested with recent WooCommerce core.

## Performance Guidance (WordPress/PHP only)
- The Orders column is computed per row:
  - Consider storing an order-level aggregate meta on checkout to avoid iterating items for large lists.
  - Keep the column hidden when not needed via Screen Options.
  - Avoid heavy work in list tables; rely on pagination and small page sizes.

## Improvements / Next steps (Optional)
- AI gift message suggestions: add a “Suggest message” button (uses OpenAI). Optional setting to enable and store API key.
- Settings: change max length and label; enable/disable the feature globally.
- Per‑product toggle: turn the field on/off per product.
- Faster Orders list: save a short order‑level summary at checkout to avoid re‑processing items.
- Accessibility & translations: ARIA live counter; add a `.pot` file.
- Tests & packaging: unit/E2E tests; WordPress.org `readme.txt` and screenshots.

## Versioning & Changelog
- Use semantic versioning (e.g., 1.0.0, 1.1.0)
- Update `README.md` Changelog section for each release.
