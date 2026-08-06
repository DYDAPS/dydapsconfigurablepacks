<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackStockCalculator
{
    private PackStockRepository $stockRepository;

    public function __construct(PackStockRepository $stockRepository)
    {
        $this->stockRepository = $stockRepository;
    }

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
