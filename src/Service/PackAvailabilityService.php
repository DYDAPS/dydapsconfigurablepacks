<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Enforces stock availability for configured packs before cart insertion.
 */
final class PackAvailabilityService
{
    private PackStockCalculator $stockCalculator;

    /**
     * @param PackStockCalculator $stockCalculator Stock calculator dependency.
     *
     * @return void
     */
    public function __construct(PackStockCalculator $stockCalculator)
    {
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Ensure the requested configuration can be fulfilled from component stock.
     *
     * @param PackConfiguration $configuration Selected pack configuration.
     * @param int $idShop Shop identifier used for stock lookup.
     *
     * @return void
     *
     * @throws \RuntimeException When the requested quantity exceeds available component stock.
     */
    public function assertAvailable(PackConfiguration $configuration, int $idShop): void
    {
        if ($configuration->getQuantity() > $this->stockCalculator->getMaximumAvailableQuantity($configuration, $idShop)) {
            throw new \RuntimeException('Selected pack configuration is not available.');
        }
    }
}
