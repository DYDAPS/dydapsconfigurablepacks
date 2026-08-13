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
 * Clean up legacy module settings and refresh the generated routes for the
 * reworked pack management interface.
 *
 * @param Module $module installed module instance
 *
 * @return bool true when the cleanup and route refresh succeeds
 */
function upgrade_module_1_2_0(Module $module): bool
{
    Configuration::deleteByName('DYDAPS_CONFIGURABLE_PACKS_DELETE_DATA');
    Configuration::deleteByName('DYDAPS_CONFIGURABLE_PACKS_ROUND_PRECISION');

    $configDir = dirname(__DIR__) . '/config/';
    $template = version_compare(_PS_VERSION_, '9.0.0', '>=')
        ? $configDir . 'routes.yml.dist'
        : $configDir . 'routes_legacy.yml.dist';

    if (is_file($template) && !@copy($template, $configDir . 'routes.yml')) {
        return false;
    }

    return true;
}
