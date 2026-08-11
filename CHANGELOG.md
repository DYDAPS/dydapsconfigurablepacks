# Changelog

## 1.1.0 - 2026-08-11

- Added a visual back-office component builder for configurable pack definitions.
- Added product and combination search for pack component selection.
- Added server-side validation for builder payloads before component persistence.
- Improved back-office component labels so merchants no longer need to edit JSON manually.
- Improved historical order display with component, price, tax and recorded refund details from immutable snapshots.
- Added remaining pack-refund quantity validation to prevent over-refunding through the module refund service.
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
