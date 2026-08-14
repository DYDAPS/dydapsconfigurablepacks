<?php
/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    DYDAPS
 * @copyright 2007-2026 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Add front-office stock display and out-of-stock ordering flags for packs.
 *
 * @param Module $module installed module instance
 *
 * @return bool true when the schema migration succeeds
 */
function upgrade_module_1_4_1(Module $module): bool
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'dydaps_pack';

    $columns = [
        'show_stock_badge' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `stock_behavior`',
        'allow_oos_order' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `show_stock_badge`',
    ];

    foreach ($columns as $column => $definition) {
        $hasColumn = (int) $db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "' . pSQL($table) . '"
            AND COLUMN_NAME = "' . pSQL($column) . '"'
        );
        if ($hasColumn === 0 && !$db->execute('ALTER TABLE `' . $table . '` ADD `' . bqSQL($column) . '` ' . $definition)) {
            return false;
        }
    }

    return true;
}
