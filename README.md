# DYDAPS - Configurable Packs

## Overview

DYDAPS - Configurable Packs is a PrestaShop module that creates configurable catalog packs from native products and combinations.

It follows the DYDAPS module architecture: Symfony back-office controllers, Twig templates, PrestaShop Grid, explicit Symfony services, repositories, PSR-4 autoloading and the new translation system.

## Features

- Pack activation on a native PrestaShop product
- Fixed components and allowed product/combination selections
- Fixed quantity components
- Fixed price, component sum, percentage discount, fixed discount and forced price strategies
- Server-side configuration hash generation
- Server-side price and stock validation
- Distinct native cart lines through PrestaShop customization identifiers
- Cart synchronization after native quantity, removal and clear operations
- Immutable order snapshots with component rows
- Idempotent stock decrement and restoration operations
- Full pack refund calculation from stored order allocation
- Multishop-aware pack definitions
- English and French translation catalogs

## Back Office Access

After installation, click **Configure** on the module page or open the **Configurable Packs** tab in the catalog menu.

The current release provides the production data model and pack grid. Product-page integration links merchants to the pack manager instead of replacing the native product editor.

## Technical Notes

The module uses native PrestaShop products as the visible sellable pack product. Each configured line is backed by a native customization row so PrestaShop can keep multiple configurations of the same product separate in cart and order details.

When `stock_behavior = components`, component stock is the business source of truth. PrestaShop still performs its native container product movement during order validation, so the module records an idempotent compensating movement for the container and manages component movements in `ps_dydaps_pack_stock_operation`. The container product is also configured to allow orders so checkout is not blocked by its own stock level.

When `stock_behavior = validate_only`, component stock is checked before cart insertion but the module does not decrement or restore component stock.

## Development workflow

- Database schema lives in `sql/install.sql` and `sql/uninstall.sql`.
- Services are explicitly registered in `config/services.yml`.
- Routes are generated from `config/routes.yml.dist` or `config/routes_legacy.yml.dist`.
- Business logic belongs in `src/Service`.
- Database access belongs in `src/Repository`.

Before packaging a release, run:

```console
composer validate --no-check-publish
composer dump-autoload
vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no
```
