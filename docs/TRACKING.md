# Tracking: GA4 ecommerce through Google Tag Manager

| Piece | Where |
|---|---|
| dataLayer events, Consent Mode v2, GTM loader | `wp-content/mu-plugins/pp-tracking.php` (no plugin from a marketplace; about 300 lines) |
| GTM container (GA4 Google tag + one GA4 event tag per ecommerce event) | container `GTM-WBDM5JQ9`, published; it is exactly `gtm/container.json` (from my tracking-kit, IDs filled in, plus the opt-in `debug_mode` parameter on each event tag) |
| GA4 property | "Pear & Porcelain (demo store)", measurement ID `G-0MCNTZPGY7`, test property for this demo only |
| Automated check of the events | `tests/tracking/walk.ts` + the tracking-kit validator (`tests/tracking/validator.ts`) |

## Events

| Event | When | How |
|---|---|---|
| `view_item_list` | home, shop, category pages, related products | printed in the page footer, one per product grid, with `item_list_id`/`item_list_name` and `index` |
| `view_item` | product page | printed in the page footer |
| `add_to_cart` | product form, grid buttons (Store API), quantity raised in the cart | queued in the visitor's WooCommerce session, collected by the browser from `POST /wp-json/pp/v1/dl-queue` |
| `remove_from_cart` | removed or quantity lowered in the cart | same queue |
| `view_cart` | cart page | printed in the footer (cart pages are never cached) |
| `begin_checkout` | checkout page | printed in the footer |
| `purchase` | order-received page, only for the visitor holding the order key | printed once; the order is marked `_pp_ga4_purchase_sent`, so a reload does not count it twice |

Every push is preceded by `{ ecommerce: null }`. `value` is the sum of price x quantity (tax and shipping left out, in
their own parameters on `purchase`), currency is `USD`, items carry `item_id` (SKU), `item_name`, `item_brand`,
`item_category`, `price`, `quantity`. No email, name or address is ever pushed (checked by the validator).

**Why a queue for cart events:** the home, shop and product pages are served from the page cache, so anything that
depends on one visitor cannot be printed into the HTML (the next visitor would get it). Cart changes go into the
visitor's own session, a cookie tells the browser there is something to collect, and a small script fetches and pushes
it. This works for the classic add-to-cart form, the block grid buttons (Store API) and the block cart.

## Consent

Consent Mode v2 defaults are `denied` for all four types and are set before anything else. Google Tag Manager itself is
not requested until the visitor clicks "Accept analytics" (stored in `localStorage`, replayed on the next pages).
Advertising consent stays denied: the store has no ad tags.

## Verification (2026-11-08)

1. **Automated, offline** (`npx tsx tests/tracking/walk.ts`): headless Chrome walks home, shop, product, add 2 from
   the product form, add one from a grid button, cart, checkout with made-up details, place order, reload the
   thank-you page. The tracking-kit validator passes 11 of 11 checks (consent default first, no hit before consent,
   `ecommerce: null` before each push, required parameters, item fields, numeric money, one currency, value = items,
   unique transaction ID, funnel order, no personal data) and the walk checks that `purchase` fired exactly once and
   `add_to_cart` twice. Google requests are answered locally in this mode. Also runs in CI.
2. **Live, into the real GA4 property** (`LIVE=1 npx tsx tests/tracking/walk.ts`): the same walk with Google requests
   let through; GA4 hits to `G-0MCNTZPGY7` were recorded. **GA4 Realtime** then listed `view_item_list`, `view_item`,
   `add_to_cart`, `view_cart`, `begin_checkout` and `purchase` (2 purchases, counted as key events): screenshots
   `reports/ga4/realtime-events-1.png` and `-2.png`.

3. **GA4 DebugView** (2026-11-09): the same live walk, started with `?pp_ga_debug=1`. DebugView showed the whole
   funnel from one debug device: `view_item_list`, `view_item`, `add_to_cart`, `view_cart`, `begin_checkout`, `purchase`
   (purchase with its parameters and both products, PP-013 and PP-001). Screenshots: `reports/ga4/debugview-funnel.jpg`,
   `debugview-purchase-params.jpg`, `debugview-purchase-items.jpg`. Only made-up checkout details were used, and
   nothing personal is visible in them.

### The debug switch

`?pp_ga_debug=1` sets a one-hour cookie; the store then pushes `{ pp_ga_debug: true }` to the dataLayer before GTM
loads, and each GA4 event tag sends it as the `debug_mode` event parameter. `?pp_ga_debug=0` clears it. Normal visitors
never have the cookie, and their hits carry no debug flag (checked). What did **not** work, for the record: putting
`debug_mode` only in the Google tag's configuration or shared event settings. GTM read the value (`true`), but the GA4
event tags did not inherit it and no hit was marked; setting it on each event tag did.

The first DebugView attempt (2026-11-08) also failed in the user's own Chrome, where an extension blocks
googletagmanager.com, so the walk runs in a separate browser. Headless Chrome identifies itself as "HeadlessChrome",
which GA4 drops as bot traffic, so live runs use the normal Chrome user agent.
