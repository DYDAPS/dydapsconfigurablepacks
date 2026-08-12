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

namespace Dydaps\ConfigurablePacks\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Contains calculated pack prices and per-component allocation data.
 *
 * Monetary values are stored in the active PrestaShop currency with six-decimal
 * precision. Unit amounts are for one configured pack; total amounts multiply
 * that unit price by the requested pack quantity.
 */
final class PackPrice
{
    /**
     * Unit pack price excluding tax.
     */
    public float $unitTaxExcl;

    /**
     * Unit pack price including tax.
     */
    public float $unitTaxIncl;

    /**
     * Total pack price excluding tax for the requested quantity.
     */
    public float $totalTaxExcl;

    /**
     * Total pack price including tax for the requested quantity.
     */
    public float $totalTaxIncl;

    /**
     * Component price rows enriched with allocated pack-level discounts.
     *
     * @var list<array<string, mixed>>
     */
    public array $allocations;

    /**
     * @param float $unitTaxExcl unit pack price excluding tax
     * @param float $unitTaxIncl unit pack price including tax
     * @param int $quantity requested pack quantity
     * @param list<array<string, mixed>> $allocations component allocation rows used for snapshots and refunds
     *
     * @return void
     */
    public function __construct(float $unitTaxExcl, float $unitTaxIncl, int $quantity, array $allocations)
    {
        $this->unitTaxExcl = $unitTaxExcl;
        $this->unitTaxIncl = $unitTaxIncl;
        $this->totalTaxExcl = $unitTaxExcl * $quantity;
        $this->totalTaxIncl = $unitTaxIncl * $quantity;
        $this->allocations = $allocations;
    }
}
