<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackDiscountAllocator
{
    /**
     * @param array<int,array<string,mixed>> $components
     *
     * @return array<int,array<string,mixed>>
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
