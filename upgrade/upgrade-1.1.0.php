<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module schema and hooks for native refund/document integration.
 *
 * @param Module $module Installed module instance.
 *
 * @return bool True when the schema and hook migration succeeds.
 */
function upgrade_module_1_1_0(Module $module): bool
{
    $db = Db::getInstance();

    $columns = [
        'id_pack_order_component' => 'ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD `id_pack_order_component` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_pack_order`',
        'id_order_slip' => 'ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD `id_order_slip` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_pack_order_component`',
        'operation_key' => 'ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD `operation_key` VARCHAR(190) NOT NULL DEFAULT "" AFTER `id_order_slip`',
    ];

    foreach ($columns as $column => $sql) {
        if (!dydaps_configurable_packs_110_column_exists('dydaps_pack_refund', $column) && !$db->execute($sql)) {
            return false;
        }
    }

    if (!dydaps_configurable_packs_110_index_exists('dydaps_pack_refund', 'operation_key')) {
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'dydaps_pack_refund`
            SET operation_key = CONCAT("legacy:", id_pack_refund)
            WHERE operation_key = ""'
        );
        if (!$db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'dydaps_pack_refund` ADD UNIQUE KEY `operation_key` (`operation_key`)')) {
            return false;
        }
    }

    foreach (dydaps_configurable_packs_110_required_hooks() as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    return true;
}

/**
 * Return every hook required by module version 1.1.0.
 *
 * @return array<int, string> Hook names.
 */
if (!function_exists('dydaps_configurable_packs_110_required_hooks')) {
    function dydaps_configurable_packs_110_required_hooks(): array
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
            'actionProductCancel',
            'actionOrderSlipAdd',
            'displayPDFInvoice',
            'displayPDFDeliverySlip',
            'displayPDFOrderSlip',
            'actionEmailSendBefore',
        ];
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
if (!function_exists('dydaps_configurable_packs_110_column_exists')) {
    function dydaps_configurable_packs_110_column_exists(string $table, string $column): bool
    {
        return (int) Db::getInstance()->getValue(
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
if (!function_exists('dydaps_configurable_packs_110_index_exists')) {
    function dydaps_configurable_packs_110_index_exists(string $table, string $index): bool
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "' . pSQL(_DB_PREFIX_ . $table) . '"
            AND INDEX_NAME = "' . pSQL($index) . '"'
        ) > 0;
    }
}
