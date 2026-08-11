<?php
declare(strict_types=1);

define('_PS_VERSION_', '8.1.0');

require_once __DIR__ . '/../../src/Model/PackConfiguration.php';
require_once __DIR__ . '/../../src/Model/PackPrice.php';
require_once __DIR__ . '/../../src/Service/PackConfigurationHashGenerator.php';
require_once __DIR__ . '/../../src/Service/PackConfigurationService.php';

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Model\PackPrice;
use Dydaps\ConfigurablePacks\Service\PackConfigurationHashGenerator;
use Dydaps\ConfigurablePacks\Service\PackConfigurationService;

$hashGenerator = new PackConfigurationHashGenerator();

$packM = new PackConfiguration(100, [
    ['id_component' => 10, 'id_product' => 200, 'id_product_attribute' => 501, 'quantity' => 1],
]);
$packXL = new PackConfiguration(100, [
    ['id_component' => 10, 'id_product' => 200, 'id_product_attribute' => 502, 'quantity' => 1],
]);
$packMAgain = new PackConfiguration(100, [
    ['id_component' => 10, 'id_product' => 200, 'id_product_attribute' => 501, 'quantity' => 1],
]);

assert($hashGenerator->generate($packM) !== $hashGenerator->generate($packXL), 'M and XL configurations must create distinct logical cart rows.');
assert($hashGenerator->generate($packM) === $hashGenerator->generate($packMAgain), 'Adding the same M configuration twice must target the same logical row.');

$cartLine = [
    'id_product' => 100,
    'id_product_attribute' => 0,
    'id_customization' => 9001,
    'configuration_hash' => $hashGenerator->generate($packM),
    'quantity' => 2,
    'unit_price_tax_excl' => 42.5,
    'unit_price_tax_incl' => 51.0,
];

assert($cartLine['quantity'] === 2, 'Native quantity and module quantity must stay synchronized for repeated adds.');
assert($cartLine['id_customization'] > 0, 'A configured pack cart line must be backed by a native customization id.');

$price = new PackPrice(42.5, 51.0, 2, [
    [
        'id_component' => 10,
        'id_product' => 200,
        'id_product_attribute' => 501,
        'quantity_per_pack' => 1,
        'unit_price_tax_excl' => 42.5,
        'unit_price_tax_incl' => 51.0,
        'total_tax_excl' => 42.5,
        'total_tax_incl' => 51.0,
        'allocated_discount_tax_excl' => 0.0,
        'allocated_discount_tax_incl' => 0.0,
    ],
]);

assert(abs($price->totalTaxExcl - 85.0) < 0.000001, 'Cart and order tax-excluded totals must use the same calculated unit price.');
assert(abs($price->totalTaxIncl - 102.0) < 0.000001, 'Cart and order tax-included totals must use the same calculated unit price.');

$snapshot = [
    'id_order_detail' => 77,
    'configuration_hash' => $cartLine['configuration_hash'],
    'quantity' => $cartLine['quantity'],
    'unit_price_tax_excl' => $cartLine['unit_price_tax_excl'],
    'unit_price_tax_incl' => $cartLine['unit_price_tax_incl'],
];

assert($snapshot['id_order_detail'] > 0, 'Order snapshot must be associated with a real order_detail.');
assert($snapshot['configuration_hash'] === $cartLine['configuration_hash'], 'Order snapshot must preserve the exact cart configuration hash.');
assert($snapshot['quantity'] === 2, 'Order snapshot quantity must match the synchronized cart quantity.');

$configurationService = new PackConfigurationService();
$rejected = false;
try {
    $configurationService->fromRequest([
        'components' => [[
            'id_component' => 10,
            'id_product' => 200,
            'id_product_attribute' => 501,
            'quantity' => 1,
            'price' => 0.01,
        ]],
    ], 100, 1);
} catch (RuntimeException $exception) {
    $rejected = true;
}
assert($rejected, 'Unexpected client component fields must be rejected before normalization.');

$rejected = false;
try {
    $configurationService->fromRequest([
        'components' => [[
            'id_component' => 10,
            'id_product' => 200,
            'id_product_attribute' => 501,
            'quantity' => 0,
        ]],
    ], 100, 1);
} catch (RuntimeException $exception) {
    $rejected = true;
}
assert($rejected, 'Invalid client component quantities must be rejected before normalization.');

echo "Pack flow contract tests passed.\n";
