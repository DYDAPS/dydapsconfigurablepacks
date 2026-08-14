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
 * Add the component customization flag and refresh the generated routes for
 * the reworked pack form.
 *
 * @param Module $module installed module instance
 *
 * @return bool true when the schema migration and route refresh succeed
 */
function upgrade_module_1_3_0(Module $module): bool
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'dydaps_pack_component';

    $hasColumn = (int) $db->getValue(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = "' . pSQL($table) . '"
        AND COLUMN_NAME = "allow_customization"'
    );
    if ($hasColumn === 0 && !$db->execute('ALTER TABLE `' . $table . '` ADD `allow_customization` TINYINT(1) NOT NULL DEFAULT 0 AFTER `surcharge_tax_excl`')) {
        return false;
    }

    $configDir = dirname(__DIR__) . '/config/';
    $template = version_compare(_PS_VERSION_, '9.0.0', '>=')
        ? $configDir . 'routes.yml.dist'
        : $configDir . 'routes_legacy.yml.dist';

    if (is_file($template) && !@copy($template, $configDir . 'routes.yml')) {
        return false;
    }

    if (!$module->registerHook('displayCartExtraProductInfo')) {
        return false;
    }

    return true;
}
