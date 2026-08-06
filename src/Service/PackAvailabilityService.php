<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackAvailabilityService
{
    private PackStockCalculator $stockCalculator;

    public function __construct(PackStockCalculator $stockCalculator)
    {
        $this->stockCalculator = $stockCalculator;
    }

    public function assertAvailable(PackConfiguration $configuration, int $idShop): void
    {
        if ($configuration->getQuantity() > $this->stockCalculator->getMaximumAvailableQuantity($configuration, $idShop)) {
            throw new \RuntimeException('Selected pack configuration is not available.');
        }
    }
}
