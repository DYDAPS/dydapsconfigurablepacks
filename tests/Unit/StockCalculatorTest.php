<?php
declare(strict_types=1);

define('_PS_VERSION_', '8.1.0');

require_once __DIR__ . '/../../src/Model/PackConfiguration.php';
require_once __DIR__ . '/../../src/Repository/PackStockRepository.php';
require_once __DIR__ . '/../../src/Service/PackStockCalculator.php';

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;
use Dydaps\ConfigurablePacks\Service\PackStockCalculator;

final class FakePackStockRepository extends PackStockRepository
{
    public function getAvailableQuantity(int $idProduct, int $idProductAttribute, int $idShop): int
    {
        return [10 => 6, 20 => 9][$idProduct] ?? 0;
    }
}

$calculator = new PackStockCalculator(new FakePackStockRepository());
$configuration = new PackConfiguration(99, [
    ['id_component' => 1, 'id_product' => 10, 'id_product_attribute' => 0, 'quantity' => 2],
    ['id_component' => 2, 'id_product' => 20, 'id_product_attribute' => 0, 'quantity' => 3],
]);

assert($calculator->getMaximumAvailableQuantity($configuration, 1) === 3);

echo "Stock calculator tests passed.\n";
