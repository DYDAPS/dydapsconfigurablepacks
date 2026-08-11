CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack` (
  `id_pack` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 0,
  `pack_type` VARCHAR(32) NOT NULL DEFAULT 'fixed',
  `pricing_method` VARCHAR(32) NOT NULL DEFAULT 'fixed',
  `fixed_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `forced_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `global_discount_percent` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `global_discount_amount_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `stock_behavior` VARCHAR(32) NOT NULL DEFAULT 'components',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id_pack`),
  UNIQUE KEY `product_shop` (`id_product`, `id_shop`),
  KEY `active_shop` (`active`, `id_shop`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_lang` (
  `id_pack` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `display_name` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id_pack`, `id_lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_component` (
  `id_component` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pack` INT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `component_type` VARCHAR(32) NOT NULL DEFAULT 'fixed',
  `optional` TINYINT(1) NOT NULL DEFAULT 0,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `min_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `max_quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `pricing_behavior` VARCHAR(32) NOT NULL DEFAULT 'native',
  `fixed_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `discount_percent` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `surcharge_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_component`),
  KEY `pack_position` (`id_pack`, `position`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_component_lang` (
  `id_component` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id_component`, `id_lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_component_product` (
  `id_component_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_component` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_component_product`),
  KEY `component_position` (`id_component`, `position`),
  KEY `product_attribute` (`id_product`, `id_product_attribute`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_cart` (
  `id_cart_pack` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cart` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_customization` INT UNSIGNED NOT NULL DEFAULT 0,
  `configuration_hash` CHAR(64) NOT NULL,
  `configuration_json` MEDIUMTEXT NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_cart_pack`),
  -- The configuration hash distinguishes multiple configurations of the same
  -- pack product inside a single cart.
  UNIQUE KEY `cart_product_hash` (`id_cart`, `id_product`, `id_product_attribute`, `configuration_hash`),
  KEY `cart_customization` (`id_cart`, `id_customization`),
  KEY `cart` (`id_cart`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_order` (
  `id_pack_order` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_order` INT UNSIGNED NOT NULL,
  `id_order_detail` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_cart` INT UNSIGNED NOT NULL,
  `id_customization` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_pack` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `id_currency` INT UNSIGNED NOT NULL,
  `configuration_hash` CHAR(64) NOT NULL,
  `pack_name` VARCHAR(255) NOT NULL,
  `product_reference` VARCHAR(128) NULL DEFAULT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL,
  `total_price_tax_excl` DECIMAL(20,6) NOT NULL,
  `total_price_tax_incl` DECIMAL(20,6) NOT NULL,
  `snapshot_json` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_pack_order`),
  -- Order displays and refunds resolve pack snapshots by order and hash.
  KEY `order_hash` (`id_order`, `configuration_hash`),
  KEY `order_cart_customization_hash` (`id_order`, `id_cart`, `id_customization`, `configuration_hash`),
  KEY `cart` (`id_cart`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_order_component` (
  `id_pack_order_component` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pack_order` INT UNSIGNED NOT NULL,
  `id_component` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `component_name` VARCHAR(255) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `product_reference` VARCHAR(128) NULL DEFAULT NULL,
  `combination_reference` VARCHAR(128) NULL DEFAULT NULL,
  `attributes_text` TEXT NULL,
  `quantity_per_pack` INT UNSIGNED NOT NULL,
  `quantity_total` INT UNSIGNED NOT NULL,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL,
  `tax_rate` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `allocated_discount_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `allocated_discount_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `refundable_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  `refundable_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`id_pack_order_component`),
  KEY `pack_order` (`id_pack_order`),
  KEY `product_attribute` (`id_product`, `id_product_attribute`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_stock_operation` (
  `id_stock_operation` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_key` VARCHAR(190) NOT NULL,
  `operation_type` VARCHAR(32) NOT NULL,
  `id_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_pack_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_product` INT UNSIGNED NOT NULL,
  `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL,
  `quantity_delta` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_stock_operation`),
  -- Operation keys make stock decrements/restorations idempotent across
  -- repeated PrestaShop hook execution.
  UNIQUE KEY `operation_key` (`operation_key`),
  KEY `order_pack` (`id_order`, `id_pack_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_dydaps_pack_refund` (
  `id_pack_refund` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_order` INT UNSIGNED NOT NULL,
  `id_pack_order` INT UNSIGNED NOT NULL,
  `id_pack_order_component` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_order_slip` INT UNSIGNED NOT NULL DEFAULT 0,
  `operation_key` VARCHAR(190) NOT NULL,
  `refund_type` VARCHAR(32) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `amount_tax_excl` DECIMAL(20,6) NOT NULL,
  `amount_tax_incl` DECIMAL(20,6) NOT NULL,
  `restocked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_pack_refund`),
  UNIQUE KEY `operation_key` (`operation_key`),
  KEY `order_pack` (`id_order`, `id_pack_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;
