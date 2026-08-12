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

/**
 * Distributes a pack-level discount across component rows.
 *
 * Allocation is proportional to each component's contribution to the pack total
 * and keeps tax-excluded and tax-included amounts separate so order snapshots,
 * tax reporting and refunds can reuse the same split.
 */
final class PackDiscountAllocator
{
    /**
     * Allocate the given discount amounts to component rows.
     *
     * @param float $discountTaxExcl pack-level discount excluding tax
     * @param float $discountTaxIncl pack-level discount including tax
     * @param list<array{
     *     total_tax_excl?: float|int|string,
     *     total_tax_incl?: float|int|string
     * }&array<string, mixed>> $components Component price rows
     *
     * @return list<array<string, mixed>> component rows with allocated_discount_tax_excl and allocated_discount_tax_incl
     */
    public function allocate(float $discountTaxExcl, float $discountTaxIncl, array $components): array
    {
        $baseTaxExcl = 0.0;
        $baseTaxIncl = 0.0;
        foreach ($components as $component) {
            $baseTaxExcl += (float) ($component['total_tax_excl'] ?? 0.0);
            $baseTaxIncl += (float) ($component['total_tax_incl'] ?? 0.0);
        }

        $allocatedExcl = 0.0;
        $allocatedIncl = 0.0;
        $lastIndex = count($components) - 1;

        foreach ($components as $index => &$component) {
            if ($index === $lastIndex) {
                // Give the rounding remainder to the last component so the
                // allocated sum always matches the pack-level discount.
                $component['allocated_discount_tax_excl'] = round($discountTaxExcl - $allocatedExcl, 6);
                $component['allocated_discount_tax_incl'] = round($discountTaxIncl - $allocatedIncl, 6);
                continue;
            }

            $ratioExcl = $baseTaxExcl > 0.0 ? (float) $component['total_tax_excl'] / $baseTaxExcl : 0.0;
            $ratioIncl = $baseTaxIncl > 0.0 ? (float) $component['total_tax_incl'] / $baseTaxIncl : 0.0;
            $component['allocated_discount_tax_excl'] = round($discountTaxExcl * $ratioExcl, 6);
            $component['allocated_discount_tax_incl'] = round($discountTaxIncl * $ratioIncl, 6);
            $allocatedExcl += (float) $component['allocated_discount_tax_excl'];
            $allocatedIncl += (float) $component['allocated_discount_tax_incl'];
        }
        unset($component);

        return $components;
    }
}
