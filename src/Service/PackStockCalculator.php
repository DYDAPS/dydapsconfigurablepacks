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

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;

/**
 * Calculates available pack quantity from component stock levels.
 */
final class PackStockCalculator
{
    private PackStockRepository $stockRepository;

    /**
     * @param PackStockRepository $stockRepository stock repository dependency
     *
     * @return void
     */
    public function __construct(PackStockRepository $stockRepository)
    {
        $this->stockRepository = $stockRepository;
    }

    /**
     * Return the maximum sellable pack quantity for a concrete configuration.
     *
     * Each selected component constrains availability by its own stock divided
     * by the quantity required per pack. The smallest component capacity wins.
     *
     * @param PackConfiguration $configuration selected pack configuration
     * @param int $idShop shop identifier used for stock lookup
     *
     * @return int maximum available configured pack quantity
     */
    public function getMaximumAvailableQuantity(PackConfiguration $configuration, int $idShop): int
    {
        $max = PHP_INT_MAX;
        foreach ($configuration->getComponents() as $component) {
            $perPack = max(1, (int) ($component['quantity'] ?? 1));
            $available = $this->stockRepository->getAvailableQuantity((int) $component['id_product'], (int) ($component['id_product_attribute'] ?? 0), $idShop);
            $max = min($max, intdiv(max(0, $available), $perPack));
        }

        return $max === PHP_INT_MAX ? 0 : $max;
    }
}
