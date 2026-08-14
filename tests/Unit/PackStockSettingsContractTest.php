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
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}

$installSql = file_get_contents(__DIR__ . '/../../sql/install.sql');
$upgrade = file_get_contents(__DIR__ . '/../../upgrade/upgrade-1.4.1.php');
$form = file_get_contents(__DIR__ . '/../../src/Form/PackGeneralType.php');
$repository = file_get_contents(__DIR__ . '/../../src/Repository/PackRepository.php');
$cartService = file_get_contents(__DIR__ . '/../../src/Service/PackCartService.php');
$frontScript = file_get_contents(__DIR__ . '/../../views/js/front.js');

assert(is_string($installSql));
assert(is_string($upgrade));
assert(is_string($form));
assert(is_string($repository));
assert(is_string($cartService));
assert(is_string($frontScript));

foreach (['show_stock_badge', 'allow_oos_order'] as $column) {
    assert(strpos($installSql, '`' . $column . '`') !== false, 'The install schema must include ' . $column . '.');
    assert(strpos($upgrade, '\'' . $column . '\'') !== false, 'The 1.4.1 upgrade must add ' . $column . '.');
    assert(strpos($form, '\'' . $column . '\'') !== false, 'The admin form must expose ' . $column . '.');
    assert(strpos($repository, '\'' . $column . '\'') !== false, 'The repository must persist ' . $column . '.');
}

assert(strpos($cartService, 'allow_oos_order') !== false, 'Cart availability checks must honor out-of-stock pack ordering.');
assert(strpos($frontScript, 'showStockBadge') !== false, 'The front office must support hiding stock status badges.');

echo "Pack stock settings contract tests passed.\n";
