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
- Distinct cart configuration persistence
- Immutable order snapshots with component rows
- Idempotent stock decrement and restoration operations
- Full pack refund calculation from stored order allocation
- Multishop-aware pack definitions
- English and French translation catalogs

## Back Office Access

After installation, click **Configure** on the module page or open the **Configurable Packs** tab in the catalog menu.

The current release provides the production data model and pack grid. Product-page integration links merchants to the pack manager instead of replacing the native product editor.

## Technical Notes

The module uses native PrestaShop products as the visible sellable pack product. Component stock is managed by module stock operations stored in `ps_dydaps_pack_stock_operation`.

Native PrestaShop does not provide a stable cross-version hook to split same-product cart rows by arbitrary custom configuration without overrides. This release persists distinct configurations in `ps_dydaps_pack_cart` and uses deterministic hashes, but full native cart row separation may require a PrestaShop-version-specific cart customization strategy or a documented override in older shops.

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
