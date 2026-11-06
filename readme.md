# Dynamic Coupon Discount for WooCommerce

Adds a **Dynamic discount** tab to the WooCommerce coupon editor, so a single coupon can give a different discount depending on the cart total. Each rule can be a flat amount or a percentage.

**Version:** 1.0.0
**Requires:** WordPress with WooCommerce active
**Text domain:** `dynamic-coupon-discount`
**Languages:** English (default), Polish (`pl_PL`)

---

## Features

- New **Dynamic discount** tab in the coupon data panel.
- **Activate dynamic discount** checkbox to switch the feature on per coupon.
- Rules table with the columns **Price from**, **Price to**, **Discount** and **Type** (Percent or Flat).
- **Add rule** button below the table and a **−** button on each row to remove it.
- When the checkbox is on and at least one rule exists, the coupon's default amount is ignored and only the rules are used.
- If no rule matches the current cart total, the coupon is not applied and the customer sees an explanatory message.
- Translation-ready, with a Polish translation included.

## Installation

1. Copy the `dynamic-coupon-discount` folder to `wp-content/plugins/`, or upload it as a ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **Dynamic Coupon Discount for WooCommerce** under **Plugins**.

Expected folder layout:

```
dynamic-coupon-discount/
├── dynamic-coupon-discount.php
├── README.md
└── languages/
    ├── dynamic-coupon-discount.pot
    ├── dynamic-coupon-discount-pl_PL.po
    └── dynamic-coupon-discount-pl_PL.mo
```

## Usage

1. Go to **Marketing → Coupons** and create or edit a coupon.
2. Open the **Dynamic discount** tab.
3. Tick **Activate dynamic discount**.
4. Click **Add rule** and fill in each row:
   - **Price from**: lower bound of the cart total (inclusive). Empty means 0.
   - **Price to**: upper bound of the cart total (inclusive). Leave empty for no upper limit.
   - **Discount**: the discount value.
   - **Type**: *Percent (%)* or *Flat (currency)*.
5. Publish or update the coupon.

### Example

| Price from | Price to | Discount | Type    |
|-----------|----------|----------|---------|
| 100       | 199,99   | 5        | Percent |
| 200       | 499,99   | 10       | Percent |
| 500       |          | 75       | Flat    |

With this coupon, a 150 cart gets 5% off, a 300 cart gets 10% off, and a 600 cart gets a flat 75 off. A cart under 100 cannot use the coupon.

## How it works

- **Cart total used for matching:** the sum of line prices (price × quantity) before any coupon, excluding tax. Use the `dcd_cart_total` filter to change it (see below).
- **Overlapping ranges:** rules are sorted by *Price from*, and the first matching rule wins.
- **Flat discounts** are applied as a fixed cart discount, **percent discounts** as a percentage of the cart. WooCommerce caps a fixed cart discount at the cart total.
- **Other coupon settings** (usage limits, expiry, individual use, product restrictions and so on) keep working as normal. The plugin only overrides the discount type and amount.
- The coupon is re-checked whenever the cart changes. If the cart total moves outside every range, the coupon is removed with a message.

## Entering prices

Type values in the format your store is configured for (**WooCommerce → Settings → General → Decimal separator**). Do not use thousands separators: enter `1500`, not `1 500` or `1.500`.

Using the wrong decimal separator can produce incorrect values. For example, typing `99,50` in a store that uses `.` can be read as `9950`.

## Developer notes

### Filter: `dcd_cart_total`

Change the cart total that rules are matched against, for example to include tax:

```php
add_filter( 'dcd_cart_total', function ( $total, $cart ) {
	return (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
}, 10, 2 );
```

### Stored data

Stored as post meta on the coupon:

| Meta key        | Value                                                       |
|-----------------|-------------------------------------------------------------|
| `_dcd_enabled`  | `yes` or `no`                                               |
| `_dcd_rules`    | array of `from`, `to`, `discount` and `type` (`percent` or `flat`) |

### Hooks used

- `woocommerce_coupon_data_tabs`, `woocommerce_coupon_data_panels`, `woocommerce_coupon_options_save`: admin tab, panel and saving.
- `woocommerce_coupon_get_discount_type`, `woocommerce_coupon_get_amount`: override the coupon's type and amount for the current cart.
- `woocommerce_coupon_is_valid`: rejects the coupon when no rule matches.

## Translations

The plugin loads translations from the `languages/` folder.

- `dynamic-coupon-discount.pot` is the template listing all translatable strings.
- To add a language, copy the `.pot` to `dynamic-coupon-discount-xx_XX.po`, translate it, and compile it to `.mo`.
- WordPress reads only the `.mo` file. Recompile it after every change to the `.po`:

```
msgfmt -o languages/dynamic-coupon-discount-xx_XX.mo languages/dynamic-coupon-discount-xx_XX.po
```

You can also use Poedit, which compiles the `.mo` when you save, or WP-CLI: `wp i18n make-mo languages`.

## Changelog

### 1.0.0
- Initial release: Dynamic discount coupon tab with flat and percent rules based on cart total.
- Polish translation.