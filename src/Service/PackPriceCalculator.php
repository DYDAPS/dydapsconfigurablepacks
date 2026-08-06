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

/**
 * Calculates catalog prices for configured packs.
 *
 * The calculator reads current PrestaShop product prices for the selected
 * components, applies the pack pricing strategy, then returns both unit prices
 * and allocation rows suitable for cart storage and immutable order snapshots.
 */
final class PackPriceCalculator
{
    private PackRepository $repository;
    private PackDiscountAllocator $allocator;

    /**
     * @param PackRepository $repository Repository used to load pack definitions.
     * @param PackDiscountAllocator $allocator Service used to split pack-level discounts by component.
     *
     * @return void
     */
    public function __construct(PackRepository $repository, PackDiscountAllocator $allocator)
    {
        $this->repository = $repository;
        $this->allocator = $allocator;
    }

    /**
     * Calculate the price of a configured pack in the active shop context.
     *
     * @param PackConfiguration $configuration Selected products, combinations and requested pack quantity.
     * @param int $idShop Shop used for pack lookup and product price loading.
     * @param int $idLang Language used for product names in allocation rows.
     * @param int $idCurrency Currency context for PrestaShop price rules.
     * @param int $idCustomer Customer context for catalog-specific price rules.
     *
     * @return PackPrice Unit and total pack prices, plus component discount allocation rows.
     */
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
                // Fixed tax-excluded prices are converted with the weighted
                // component tax ratio to preserve the configured component mix.
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
     * Build component price rows from current catalog prices.
     *
     * @param PackConfiguration $configuration Selected pack configuration.
     * @param int $idShop Shop identifier used to load products.
     * @param int $idLang Language identifier used to load product names.
     * @param int $idCurrency Currency identifier for price context.
     * @param int $idCustomer Customer identifier for specific prices.
     *
     * @return list<array{
     *     id_component: int,
     *     id_product: int,
     *     id_product_attribute: int,
     *     quantity_per_pack: int,
     *     unit_price_tax_excl: float,
     *     unit_price_tax_incl: float,
     *     total_tax_excl: float,
     *     total_tax_incl: float,
     *     tax_rate: float,
     *     product_name: string,
     *     product_reference: string
     * }>
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

    /**
     * Convert a tax-excluded amount using the weighted tax ratio of components.
     *
     * This is used when a pack-level fixed amount must be represented as a
     * tax-included amount although selected components can have different rates.
     *
     * @param float $amountTaxExcl Amount excluding tax to convert.
     * @param float $baseTaxExcl Component total excluding tax.
     * @param float $baseTaxIncl Component total including tax.
     *
     * @return float Weighted tax-included amount.
     */
    private function convertTaxExclToWeightedTaxIncl(float $amountTaxExcl, float $baseTaxExcl, float $baseTaxIncl): float
    {
        if ($baseTaxExcl <= 0.0) {
            return $amountTaxExcl;
        }

        return $amountTaxExcl * ($baseTaxIncl / $baseTaxExcl);
    }
}
