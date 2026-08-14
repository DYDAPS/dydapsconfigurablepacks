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

$module = file_get_contents(__DIR__ . '/../../dydapsconfigurablepacks.php');

assert(is_string($module));

assert(strpos($module, 'displayCheckoutSummaryBottom') !== false, 'Pack customization fee totals must be registered in the checkout summary bottom hook.');
assert(strpos($module, 'displayCheckoutSummaryTop') !== false, 'Pack customization fee totals must be registered in the checkout summary top hook.');
assert(strpos($module, 'public function hookDisplayCheckoutSummaryBottom(array $params): string') !== false, 'The module must render pack customization fees in checkout summary bottom.');
assert(strpos($module, 'public function hookDisplayCheckoutSummaryTop(array $params): string') !== false, 'The module must render pack customization fees in checkout summary top.');
assert(substr_count($module, 'return $this->renderPackCustomizationFeeSummary($params);') >= 3, 'Cart and checkout hooks must share the same pack customization fee summary renderer.');
assert(strpos($module, 'views/templates/hook/cart_fee_summary.tpl') !== false, 'The shared renderer must keep using the customization fee summary markup.');

echo "Checkout customization fee contract tests passed.\n";
