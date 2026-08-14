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

use Dydaps\ConfigurablePacks\Config\PackConfig;
use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Model\PackPrice;
use Dydaps\ConfigurablePacks\Repository\PackRepository;

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
    private \Context $context;
    private ?PackCustomizationFeeCalculator $feeCalculator;

    /**
     * @param PackRepository $repository repository used to load pack definitions
     * @param PackDiscountAllocator $allocator service used to split pack-level discounts by component
     * @param \Context $context injected legacy context used to build the temporary price context
     * @param PackCustomizationFeeCalculator|null $feeCalculator optional calculator applying customization fees per component
     *
     * @return void
     */
    public function __construct(PackRepository $repository, PackDiscountAllocator $allocator, \Context $context, ?PackCustomizationFeeCalculator $feeCalculator = null)
    {
        $this->repository = $repository;
        $this->allocator = $allocator;
        $this->context = $context;
        $this->feeCalculator = $feeCalculator;
    }

    /**
     * Calculate the price of a configured pack in the active shop context.
     *
     * @param PackConfiguration $configuration selected products, combinations and requested pack quantity
     * @param int $idShop shop used for pack lookup and product price loading
     * @param int $idLang language used for product names in allocation rows
     * @param int $idCurrency currency context for PrestaShop price rules
     * @param int $idCustomer customer context for catalog-specific price rules
     *
     * @return PackPrice unit and total pack prices, plus component discount allocation rows
     */
    public function calculate(PackConfiguration $configuration, int $idShop, int $idLang, int $idCurrency, int $idCustomer = 0): PackPrice
    {
        $pack = $this->repository->getPackByProduct($configuration->getIdProduct(), $idShop);
        if (!$pack) {
            return new PackPrice(0.0, 0.0, $configuration->getQuantity(), []);
        }

        $components = $this->buildComponentPriceRows($configuration, (int) $pack['id_pack'], $idShop, $idLang, $idCurrency, $idCustomer);
        $sumTaxExcl = array_sum(array_map(static fn (array $row): float => (float) $row['total_tax_excl'], $components));
        $sumTaxIncl = array_sum(array_map(static fn (array $row): float => (float) $row['total_tax_incl'], $components));
        $customizationFeesTaxExcl = array_sum(array_map(static fn (array $row): float => (float) $row['customization_fee_tax_excl'], $components));
        $customizationFeesTaxIncl = array_sum(array_map(static fn (array $row): float => (float) $row['customization_fee_tax_incl'], $components));

        $unitTaxExcl = $sumTaxExcl;
        $unitTaxIncl = $sumTaxIncl;
        $discountTaxExcl = 0.0;
        $discountTaxIncl = 0.0;

        switch ((string) $pack['pricing_method']) {
            case PackConfig::PRICING_FIXED:
                $unitTaxExcl = (float) $pack['fixed_price_tax_excl'] + $customizationFeesTaxExcl;
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
                $unitTaxExcl = (float) $pack['forced_price_tax_excl'] + $customizationFeesTaxExcl;
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
     * @param PackConfiguration $configuration selected pack configuration
     * @param int $idShop shop identifier used to load products
     * @param int $idLang language identifier used to load product names
     * @param int $idCurrency currency identifier for price context
     * @param int $idCustomer customer identifier for specific prices
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
     *     product_reference: string,
     *     combination_reference: string,
     *     attributes_text: string,
     *     customization: string,
     *     customization_fee_tax_excl: float,
     *     customization_fee_tax_incl: float
     * }>
     */
    private function buildComponentPriceRows(PackConfiguration $configuration, int $idPack, int $idShop, int $idLang, int $idCurrency, int $idCustomer): array
    {
        $rows = [];
        $definitions = [];
        foreach ($this->repository->getComponents($idPack, $idLang) as $definition) {
            $definitions[(int) $definition['id_component']] = $definition;
        }
        foreach ($configuration->getComponents() as $component) {
            $idProduct = (int) $component['id_product'];
            $idAttribute = (int) ($component['id_product_attribute'] ?? 0);
            $qty = max(1, (int) ($component['quantity'] ?? 1));
            $definition = $definitions[(int) $component['id_component']] ?? [];
            $specificPriceOutput = null;
            $priceContext = $this->buildPriceContext($idShop, $idCurrency);
            $unitExcl = (float) \Product::getPriceStatic($idProduct, false, $idAttribute, 6, null, false, true, $qty, false, $idCustomer, null, null, $specificPriceOutput, true, true, $priceContext, true);
            $unitIncl = (float) \Product::getPriceStatic($idProduct, true, $idAttribute, 6, null, false, true, $qty, false, $idCustomer, null, null, $specificPriceOutput, true, true, $priceContext, true);
            $taxRatio = $unitExcl > 0 ? $unitIncl / $unitExcl : 1.0;
            $unitExcl = $this->applyComponentPricing($unitExcl, $definition);
            $unitIncl = $this->applyComponentPricing($unitIncl, $definition, $taxRatio);
            $product = new \Product($idProduct, false, $idLang, $idShop);
            $taxRate = $unitExcl > 0 ? (($unitIncl / $unitExcl) - 1) * 100 : 0.0;

            $feeTotals = [0.0, 0.0];
            if ($this->feeCalculator !== null) {
                $cart = $this->context->cart;
                $idAddressDelivery = $cart !== null && (int) ($cart->id ?? 0) > 0 ? (int) ($cart->id_address_delivery ?? 0) : 0;
                $feeTotals = $this->feeCalculator->computeTotals(
                    (array) ($component['customization_fields'] ?? []),
                    $idProduct,
                    $idShop,
                    $idCurrency,
                    $idAddressDelivery,
                    $idLang,
                    $qty
                );
            }
            $unitExcl += $feeTotals[0];
            $unitIncl += $feeTotals[1];

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
                'component_name' => (string) ($definition['name'] ?? ''),
                'product_name' => (string) $product->name,
                'product_reference' => (string) $product->reference,
                'combination_reference' => $idAttribute > 0 ? $this->repository->getCombinationReference($idAttribute) : '',
                'attributes_text' => $idAttribute > 0 ? strip_tags(\Product::getProductName($idProduct, $idAttribute, $idLang)) : '',
                'customization' => trim((string) ($component['customization'] ?? '')),
                'customization_fee_tax_excl' => $feeTotals[0],
                'customization_fee_tax_incl' => $feeTotals[1],
            ];
        }

        return $rows;
    }

    /**
     * Build a temporary PrestaShop context matching the requested shop/currency.
     *
     * @param int $idShop shop identifier
     * @param int $idCurrency currency identifier
     *
     * @return \Context price context
     */
    private function buildPriceContext(int $idShop, int $idCurrency): \Context
    {
        $context = $this->context->cloneContext();
        if ($idShop > 0) {
            $context->shop = new \Shop($idShop);
        }
        if ($idCurrency > 0) {
            $context->currency = new \Currency($idCurrency);
        }

        return $context;
    }

    /**
     * Apply component-level pricing behavior to a unit amount.
     *
     * @param float $unitAmount unit price in the current tax mode
     * @param array<string, mixed> $definition component definition
     *
     * @return float adjusted unit amount
     */
    private function applyComponentPricing(float $unitAmount, array $definition, float $taxRatio = 1.0): float
    {
        switch ((string) ($definition['pricing_behavior'] ?? 'native')) {
            case 'fixed':
                return (float) ($definition['fixed_price_tax_excl'] ?? $unitAmount) * $taxRatio;
            case 'discount_percent':
                return $unitAmount * (1 - ((float) ($definition['discount_percent'] ?? 0) / 100));
            case 'surcharge':
                return $unitAmount + ((float) ($definition['surcharge_tax_excl'] ?? 0) * $taxRatio);
            case 'native':
            default:
                return $unitAmount;
        }
    }

    /**
     * Convert a tax-excluded amount using the weighted tax ratio of components.
     *
     * This is used when a pack-level fixed amount must be represented as a
     * tax-included amount although selected components can have different rates.
     *
     * @param float $amountTaxExcl amount excluding tax to convert
     * @param float $baseTaxExcl component total excluding tax
     * @param float $baseTaxIncl component total including tax
     *
     * @return float weighted tax-included amount
     */
    private function convertTaxExclToWeightedTaxIncl(float $amountTaxExcl, float $baseTaxExcl, float $baseTaxIncl): float
    {
        if ($baseTaxExcl <= 0.0) {
            return $amountTaxExcl;
        }

        return $amountTaxExcl * ($baseTaxIncl / $baseTaxExcl);
    }
}
