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
 * Install the initial database schema when upgrading to version 1.0.0.
 *
 * @param DydapsConfigurablePacks $module module instance provided by PrestaShop
 *
 * @return bool true when the initial schema SQL executes successfully
 */
function upgrade_module_1_0_0($module): bool
{
    return $module->runSqlFile(__DIR__ . '/../sql/install.sql');
}
