<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_0($module): bool
{
    return $module->runSqlFile(__DIR__ . '/../sql/install.sql');
}
