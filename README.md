# WooCommerce Gift Message

Adds a Gift Message text field (max 150 characters) to WooCommerce product pages and persists it from cart → order → admin → emails. Includes an Orders admin column and minimal UI enhancements.

## Overview
- Frontend text input on single product pages.
- Validates (max length, nonce) and sanitizes input.
- Saves to cart item meta and order item meta.
- Displays on:
  - Cart and Checkout (under line items)
  - Order confirmation (Thank you) and My Account → Orders → View (under line items)
  - WooCommerce emails (order item meta)
  - WooCommerce admin → Orders list (column) and Order details (item meta)
- Minimal CSS and a JS live character counter.

## Main Files
- `woocommerce-gift-message.php` – Plugin bootstrap, constants, activation checks.
- `includes/class-wcgm.php` – Core functionality and all WooCommerce hooks.
- `assets/css/frontend.css` – Minimal styling for the field.
- `assets/js/frontend.js` – Live character counter enhancement.

## Key Hooks and Flow
- Render field: `woocommerce_before_add_to_cart_button`
- Validate: `woocommerce_add_to_cart_validation`
- Save to cart: `woocommerce_add_cart_item_data`
- Show in cart/checkout: `woocommerce_get_item_data`
- Save to order items: `woocommerce_checkout_create_order_line_item`
- Emails/admin: standard WooCommerce templates display item meta automatically.
- Admin Orders column: supports both legacy posts table and HPOS Orders table.

## Security
- Uses `wp_nonce_field()` and checks via `wp_verify_nonce()`.
- Sanitizes via `sanitize_text_field()` and strips newlines.
- Capability checks (`current_user_can('manage_woocommerce')`) for admin columns.
- All output escaped via `esc_html()`.

## Extensibility
- Filters:
  - `wcgm_max_length` – Change max characters (default 150).
  - `wcgm_gift_message_label` – Change the display label (default "Gift Message").
- Action:
  - `wcgm_after_field` – Inject content after the input.

## Assumptions / Limitations
- One gift message per product line item. Variable products and quantities share the same message per line.
- If multiple products in an order contain messages, the admin Orders column shows a semicolon-separated list.
- The Orders column aggregates from a hidden item meta `_wcgm_gift_message` for reliability; visible meta uses the human-readable label for templates/emails.

## Performance Notes (10,000+ orders)
- The Orders column computes values on-the-fly. For large stores using standard WordPress/PHP (no external caches):
  - Persist an order-level aggregate meta (e.g., on `woocommerce_checkout_create_order`) to avoid per-request iteration when listing orders.
  - Prefer WooCommerce HPOS data stores for faster lookups and ensure queries are indexed by default keys (no custom tables required).
  - Keep the Orders column disabled via Screen Options when not needed to reduce per-row processing.
  - Avoid heavy calculations in list tables; paginate and lazy-load where possible.

## Improvements / Next steps (Optional)
- AI gift message suggestions: add a “Suggest message” button (uses OpenAI). Optional setting to enable and store API key.
- Settings: change max length and label; enable/disable the feature globally.
- Per‑product toggle: turn the field on/off per product.
- Faster Orders list: save a short order‑level summary at checkout to avoid re‑processing items.
- Accessibility & translations: ARIA live counter; add a `.pot` file.
- Tests & packaging: unit/E2E tests; WordPress.org `readme.txt` and screenshots.

## Installation
1. Create and upload the `woocommerce-gift-message` folder to `wp-content/plugins/`.
2. Activate the plugin in WordPress → Plugins.
3. Ensure WooCommerce is active. Visit a product page to use the Gift Message field.

## Changelog

### 1.0.0 — 2025-08-16
- Initial release.
- Adds Gift Message field on single product pages (max 150 characters).
- Validates and sanitizes input; saves to cart item meta and order item meta.
- Displays message on Cart, Checkout, Thank You, My Account → Orders → View, and emails.
- Adds a Gift Message column in the WooCommerce Orders admin list.
- Includes minimal CSS and a jQuery-based live character counter.
- README includes overview, hooks, security, extensibility, and performance notes (WordPress/PHP-only).
