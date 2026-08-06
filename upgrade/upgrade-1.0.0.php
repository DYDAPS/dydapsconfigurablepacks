<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Install the initial database schema when upgrading to version 1.0.0.
 *
 * @param DydapsConfigurablePacks $module Module instance provided by PrestaShop.
 *
 * @return bool True when the initial schema SQL executes successfully.
 */
function upgrade_module_1_0_0($module): bool
{
    return $module->runSqlFile(__DIR__ . '/../sql/install.sql');
}
