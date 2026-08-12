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

require_once __DIR__ . '/../../src/Model/PackConfiguration.php';
require_once __DIR__ . '/../../src/Service/PackConfigurationHashGenerator.php';
require_once __DIR__ . '/../../src/Service/PackDiscountAllocator.php';

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Service\PackConfigurationHashGenerator;
use Dydaps\ConfigurablePacks\Service\PackDiscountAllocator;

$hashGenerator = new PackConfigurationHashGenerator();

$first = new PackConfiguration(42, [
    ['id_component' => 2, 'id_product' => 20, 'id_product_attribute' => 4, 'quantity' => 1],
    ['id_component' => 1, 'id_product' => 10, 'id_product_attribute' => 2, 'quantity' => 1],
]);
$second = new PackConfiguration(42, [
    ['quantity' => 1, 'id_product_attribute' => 2, 'id_product' => 10, 'id_component' => 1],
    ['quantity' => 1, 'id_product_attribute' => 4, 'id_product' => 20, 'id_component' => 2],
]);
$third = new PackConfiguration(42, [
    ['id_component' => 1, 'id_product' => 10, 'id_product_attribute' => 3, 'quantity' => 1],
    ['id_component' => 2, 'id_product' => 20, 'id_product_attribute' => 4, 'quantity' => 1],
]);

assert($hashGenerator->generate($first) === $hashGenerator->generate($second));
assert($hashGenerator->generate($first) !== $hashGenerator->generate($third));

$allocator = new PackDiscountAllocator();
$rows = $allocator->allocate(30.0, 36.0, [
    ['total_tax_excl' => 40.0, 'total_tax_incl' => 48.0],
    ['total_tax_excl' => 60.0, 'total_tax_incl' => 72.0],
]);

assert(abs(($rows[0]['allocated_discount_tax_excl'] + $rows[1]['allocated_discount_tax_excl']) - 30.0) < 0.000001);
assert(abs(($rows[0]['allocated_discount_tax_incl'] + $rows[1]['allocated_discount_tax_incl']) - 36.0) < 0.000001);
assert(abs($rows[0]['allocated_discount_tax_excl'] - 12.0) < 0.000001);
assert(abs($rows[1]['allocated_discount_tax_excl'] - 18.0) < 0.000001);

echo "Hash and allocation tests passed.\n";
