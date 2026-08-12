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

/**
 * Enforces stock availability for configured packs before cart insertion.
 */
final class PackAvailabilityService
{
    private PackStockCalculator $stockCalculator;

    /**
     * @param PackStockCalculator $stockCalculator stock calculator dependency
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
     * @param PackConfiguration $configuration selected pack configuration
     * @param int $idShop shop identifier used for stock lookup
     *
     * @return void
     *
     * @throws \RuntimeException when the requested quantity exceeds available component stock
     */
    public function assertAvailable(PackConfiguration $configuration, int $idShop): void
    {
        if ($configuration->getQuantity() > $this->stockCalculator->getMaximumAvailableQuantity($configuration, $idShop)) {
            throw new \RuntimeException('Selected pack configuration is not available.');
        }
    }
}
