# Changelog

## 1.4.0 - 2026-08-14

- Fixed the back-office rich text editor so summary and description textareas initialize TinyMCE when their tab becomes visible and save on form submission.
- Reworked front-office declination selection for accessibility: attribute groups render as fieldsets with legends, chips keep keyboard focus, selection is announced through a live region, and unavailable combinations stay disabled.
- Added an optional/mandatory customization setting per pack component: the front-office shows "Facultatif"/"Obligatoire" and blocks add-to-cart until required customization data is provided.
- Fixed front-office flag parsing so component customization settings are honored regardless of JSON boolean type.
- Kept native PrestaShop customization fields and the DYDAPS customization fee module supported: per-field fees are shown and included in estimated totals and the calculated pack price, including fixed and forced pack pricing.
- Displayed pack component customization fee totals in the checkout summary, not only on the cart page.
- Included pack component customization fees in the native cart line price override, so cart totals match the displayed pack price.

## 1.1.0 - 2026-08-11

- Added a visual back-office component builder for configurable pack definitions.
- Added product and combination search for pack component selection.
- Added server-side validation for builder payloads before component persistence.
- Improved back-office component labels so merchants no longer need to edit JSON manually.
- Improved historical order display with component, price, tax and recorded refund details from immutable snapshots.
- Added remaining pack-refund quantity validation to prevent over-refunding through the module refund service.
- Added front-office CSRF protection for configurable pack cart mutations with a stable per-visitor token that survives cart changes.
- Added a back-office setting to preserve or remove module data during uninstall.
- Corrected back-office pack creation to require the create permission instead of update.
- Restored native grid toggle/delete requests to PrestaShop CSRF handling.
- Harmonized admin permission, CSRF and request helpers with other DYDAPS modules.
- Removed obsolete configuration, dead services and unused compatibility helpers.
- Harmonized directory protection index files.
- Expanded automated test coverage for the front token, admin ACL wiring and CSRF contracts.
- Fixed French translation catalogs encoding.
- Kept full declared compatibility scoped to PrestaShop 8.1 or later.

## 1.0.1 - 2026-08-11

- Fixed native customization-backed cart separation for multiple configurations of the same pack product.
- Added native/module cart synchronization for repeated adds, quantity updates, removals and cart deletion.
- Added rollback and orphan cleanup for failed native cart additions.
- Hardened server-side pack configuration validation and normalization before pricing, stock and order snapshots.
- Fixed order snapshot creation with reliable native `order_detail` resolution.
- Added component stock movements, container stock neutralization and idempotent cancellation restoration.
- Added PrestaShop 9 integration tests covering cart, order snapshots and stock behavior.
- Added upgrade script for cart customization identifiers, synchronized quantities and new hooks.
- Limited full declared compatibility to PrestaShop 8.1 or later, where native product price calculation hooks are available.

## 1.0.0 - 2026-08-06

- Initial DYDAPS configurable packs module.
- Added pack schema, admin grid, front configurator, cart configuration persistence, order snapshots, price calculation, stock movements and refund allocation services.
