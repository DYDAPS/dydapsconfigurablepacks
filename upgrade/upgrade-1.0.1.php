<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module schema and hooks for native customization-backed cart lines.
 *
 * @param Module $module Installed module instance.
 *
 * @return bool True when the schema and hook migration succeeds.
 */
function upgrade_module_1_0_1(Module $module): bool
{
    $db = \Db::getInstance();

    if (!dydaps_configurable_packs_column_exists('dydaps_pack_cart', 'id_customization')
        && !$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_cart` ADD `id_customization` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_product_attribute`')) {
        return false;
    }

    if (!dydaps_configurable_packs_column_exists('dydaps_pack_cart', 'quantity')
        && !$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_cart` ADD `quantity` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `configuration_json`')) {
        return false;
    }

    if (!dydaps_configurable_packs_index_exists('dydaps_pack_cart', 'cart_customization')
        && !$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_cart` ADD KEY `cart_customization` (`id_cart`, `id_customization`)')) {
        return false;
    }

    if (!dydaps_configurable_packs_column_exists('dydaps_pack_order', 'id_customization')
        && !$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_order` ADD `id_customization` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_cart`')) {
        return false;
    }

    if (!dydaps_configurable_packs_index_exists('dydaps_pack_order', 'order_cart_customization_hash')
        && !$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_order` ADD KEY `order_cart_customization_hash` (`id_order`, `id_cart`, `id_customization`, `configuration_hash`)')) {
        return false;
    }

    if (!dydaps_configurable_packs_backfill_cart_quantities()) {
        return false;
    }

    if (!dydaps_configurable_packs_cleanup_unlinked_cart_rows()) {
        return false;
    }

    foreach (dydaps_configurable_packs_required_hooks() as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    return true;
}

/**
 * Return every hook required by module version 1.0.1.
 *
 * @return array<int, string> Hook names.
 */
if (!function_exists('dydaps_configurable_packs_required_hooks')) {
    function dydaps_configurable_packs_required_hooks(): array
    {
        return [
            'displayHeader',
            'displayProductAdditionalInfo',
            'displayAdminProductsExtra',
            'actionCartSave',
            'actionObjectCartDeleteAfter',
            'actionValidateOrder',
            'actionProductPriceCalculation',
            'displayAdminOrderMain',
            'displayAdminOrderSide',
            'displayOrderDetail',
            'actionOrderStatusPostUpdate',
        ];
    }
}

/**
 * Remove cart rows that cannot be safely linked to a native customized line.
 *
 * Legacy rows without a resolvable id_customization cannot preserve the
 * M/XL distinction through native cart and order flows, so they are invalidated.
 *
 * @return bool True when cleanup succeeds.
 */
if (!function_exists('dydaps_configurable_packs_cleanup_unlinked_cart_rows')) {
    function dydaps_configurable_packs_cleanup_unlinked_cart_rows(): bool
    {
        return \Db::getInstance()->execute(
            'DELETE pc FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart` pc
            LEFT JOIN `' . _DB_PREFIX_ . 'cart_product` cp
                ON cp.id_cart = pc.id_cart
                AND cp.id_product = pc.id_product
                AND cp.id_product_attribute = pc.id_product_attribute
                AND cp.id_customization = pc.id_customization
                AND cp.quantity > 0
            LEFT JOIN `' . _DB_PREFIX_ . 'customization` c
                ON c.id_customization = pc.id_customization
                AND c.id_cart = pc.id_cart
                AND c.id_product = pc.id_product
                AND c.id_product_attribute = pc.id_product_attribute
            WHERE pc.id_customization <= 0
                OR cp.id_cart IS NULL
                OR c.id_customization IS NULL'
        );
    }
}

/**
 * Return whether a table column exists.
 *
 * @param string $table Table name without prefix.
 * @param string $column Column name.
 *
 * @return bool True when the column exists.
 */
if (!function_exists('dydaps_configurable_packs_column_exists')) {
    function dydaps_configurable_packs_column_exists(string $table, string $column): bool
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "' . pSQL(_DB_PREFIX_ . $table) . '"
            AND COLUMN_NAME = "' . pSQL($column) . '"'
        ) > 0;
    }
}

/**
 * Return whether a table index exists.
 *
 * @param string $table Table name without prefix.
 * @param string $index Index name.
 *
 * @return bool True when the index exists.
 */
if (!function_exists('dydaps_configurable_packs_index_exists')) {
    function dydaps_configurable_packs_index_exists(string $table, string $index): bool
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "' . pSQL(_DB_PREFIX_ . $table) . '"
            AND INDEX_NAME = "' . pSQL($index) . '"'
        ) > 0;
    }
}

/**
 * Backfill stored cart quantities from native cart lines when customizations already exist.
 *
 * @return bool True when the update succeeds.
 */
if (!function_exists('dydaps_configurable_packs_backfill_cart_quantities')) {
    function dydaps_configurable_packs_backfill_cart_quantities(): bool
    {
        return \Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'dydaps_pack_cart` pc
            INNER JOIN `' . _DB_PREFIX_ . 'cart_product` cp
                ON cp.id_cart = pc.id_cart
                AND cp.id_product = pc.id_product
                AND cp.id_product_attribute = pc.id_product_attribute
                AND (pc.id_customization = 0 OR cp.id_customization = pc.id_customization)
            SET pc.quantity = GREATEST(1, cp.quantity),
                pc.id_customization = IF(pc.id_customization > 0, pc.id_customization, cp.id_customization),
                pc.updated_at = NOW()
            WHERE cp.quantity > 0'
        );
    }
}
