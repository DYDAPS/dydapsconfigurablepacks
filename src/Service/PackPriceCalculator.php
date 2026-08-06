<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Config\PackConfig;
use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Model\PackPrice;
use Dydaps\ConfigurablePacks\Repository\PackRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackPriceCalculator
{
    private PackRepository $repository;
    private PackDiscountAllocator $allocator;

    public function __construct(PackRepository $repository, PackDiscountAllocator $allocator)
    {
        $this->repository = $repository;
        $this->allocator = $allocator;
    }

    public function calculate(PackConfiguration $configuration, int $idShop, int $idLang, int $idCurrency, int $idCustomer = 0): PackPrice
    {
        $pack = $this->repository->getPackByProduct($configuration->getIdProduct(), $idShop);
        if (!$pack) {
            return new PackPrice(0.0, 0.0, $configuration->getQuantity(), []);
        }

        $components = $this->buildComponentPriceRows($configuration, $idShop, $idLang, $idCurrency, $idCustomer);
        $sumTaxExcl = array_sum(array_map(static fn (array $row): float => (float) $row['total_tax_excl'], $components));
        $sumTaxIncl = array_sum(array_map(static fn (array $row): float => (float) $row['total_tax_incl'], $components));

        $unitTaxExcl = $sumTaxExcl;
        $unitTaxIncl = $sumTaxIncl;
        $discountTaxExcl = 0.0;
        $discountTaxIncl = 0.0;

        switch ((string) $pack['pricing_method']) {
            case PackConfig::PRICING_FIXED:
                $unitTaxExcl = (float) $pack['fixed_price_tax_excl'];
                $unitTaxIncl = $this->convertTaxExclToWeightedTaxIncl($unitTaxExcl, $sumTaxExcl, $sumTaxIncl);
                break;
            case PackConfig::PRICING_PERCENT_DISCOUNT:
                $discountTaxExcl = $sumTaxExcl * ((float) $pack['global_discount_percent'] / 100);
                $discountTaxIncl = $sumTaxIncl * ((float) $pack['global_discount_percent'] / 100);
                $unitTaxExcl = $sumTaxExcl - $discountTaxExcl;
                $unitTaxIncl = $sumTaxIncl - $discountTaxIncl;
                break;
            case PackConfig::PRICING_FIXED_DISCOUNT:
                $discountTaxExcl = min($sumTaxExcl, (float) $pack['global_discount_amount_tax_excl']);
                $discountTaxIncl = $this->convertTaxExclToWeightedTaxIncl($discountTaxExcl, $sumTaxExcl, $sumTaxIncl);
                $unitTaxExcl = $sumTaxExcl - $discountTaxExcl;
                $unitTaxIncl = $sumTaxIncl - $discountTaxIncl;
                break;
            case PackConfig::PRICING_FORCED:
                $unitTaxExcl = (float) $pack['forced_price_tax_excl'];
                $unitTaxIncl = $this->convertTaxExclToWeightedTaxIncl($unitTaxExcl, $sumTaxExcl, $sumTaxIncl);
                $discountTaxExcl = max(0.0, $sumTaxExcl - $unitTaxExcl);
                $discountTaxIncl = max(0.0, $sumTaxIncl - $unitTaxIncl);
                break;
            case PackConfig::PRICING_COMPONENT_SUM:
            default:
                break;
        }

        $components = $this->allocator->allocate($discountTaxExcl, $discountTaxIncl, $components);

        return new PackPrice(round($unitTaxExcl, 6), round($unitTaxIncl, 6), $configuration->getQuantity(), $components);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildComponentPriceRows(PackConfiguration $configuration, int $idShop, int $idLang, int $idCurrency, int $idCustomer): array
    {
        $rows = [];
        foreach ($configuration->getComponents() as $component) {
            $idProduct = (int) $component['id_product'];
            $idAttribute = (int) ($component['id_product_attribute'] ?? 0);
            $qty = max(1, (int) ($component['quantity'] ?? 1));
            $specificPriceOutput = null;
            $unitExcl = (float) \Product::getPriceStatic($idProduct, false, $idAttribute, 6, null, false, true, 1, false, $idCustomer, null, null, $specificPriceOutput, true, true, null, true);
            $unitIncl = (float) \Product::getPriceStatic($idProduct, true, $idAttribute, 6, null, false, true, 1, false, $idCustomer, null, null, $specificPriceOutput, true, true, null, true);
            $product = new \Product($idProduct, false, $idLang, $idShop);
            $taxRate = $unitExcl > 0 ? (($unitIncl / $unitExcl) - 1) * 100 : 0.0;

            $rows[] = [
                'id_component' => (int) $component['id_component'],
                'id_product' => $idProduct,
                'id_product_attribute' => $idAttribute,
                'quantity_per_pack' => $qty,
                'unit_price_tax_excl' => $unitExcl,
                'unit_price_tax_incl' => $unitIncl,
                'total_tax_excl' => $unitExcl * $qty,
                'total_tax_incl' => $unitIncl * $qty,
                'tax_rate' => $taxRate,
                'product_name' => (string) $product->name,
                'product_reference' => (string) $product->reference,
            ];
        }

        return $rows;
    }

    private function convertTaxExclToWeightedTaxIncl(float $amountTaxExcl, float $baseTaxExcl, float $baseTaxIncl): float
    {
        if ($baseTaxExcl <= 0.0) {
            return $amountTaxExcl;
        }

        return $amountTaxExcl * ($baseTaxIncl / $baseTaxExcl);
    }
}
