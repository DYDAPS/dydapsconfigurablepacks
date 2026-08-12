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
require_once __DIR__ . '/../../src/Repository/PackStockRepository.php';
require_once __DIR__ . '/../../src/Service/PackStockCalculator.php';

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;
use Dydaps\ConfigurablePacks\Service\PackStockCalculator;

/**
 * Test double returning deterministic stock quantities.
 */
final class FakePackStockRepository extends PackStockRepository
{
    /**
     * @param int $idProduct product identifier
     * @param int $idProductAttribute combination identifier, unused by the test double
     * @param int $idShop shop identifier, unused by the test double
     *
     * @return int deterministic stock quantity for the requested product
     *
     * {@inheritDoc}
     */
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
