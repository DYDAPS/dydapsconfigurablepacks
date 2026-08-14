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
$services = file_get_contents(__DIR__ . '/../../config/services.yml');

assert(is_string($module));
assert(is_string($services));

preg_match_all('/new PackPriceCalculator\([^;]+;/s', $module, $priceCalculatorMatches);
$fallbackPriceCalculators = $priceCalculatorMatches[0] ?? [];

assert(strpos($services, '$feeCalculator: \'@dydaps.configurable_packs.service.fee_calculator\'') !== false, 'The service price calculator must receive the pack customization fee calculator.');
assert(count($fallbackPriceCalculators) > 0, 'The module fallback paths must instantiate price calculators.');
foreach ($fallbackPriceCalculators as $fallbackPriceCalculator) {
    assert(strpos($fallbackPriceCalculator, 'new PackCustomizationFeeCalculator()') !== false, 'Fallback price calculators must include pack customization fees.');
}
assert(strpos($module, 'public function hookActionProductPriceCalculation(array &$params): void') !== false, 'The module must keep overriding cart product prices.');

echo "Pack customization fee price contract tests passed.\n";
