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
 * Computes customization fees for pack components without coupling to the
 * customization fee module.
 *
 * The fee module only charges native customized_data rows that reference a
 * customization field of the product carried by the cart line. Pack components
 * are never cart lines, so the module's own hooks can never tax them. This
 * calculator replicates the same configuration semantics by reading the fee
 * table directly, guaranteeing identical amounts with no double counting:
 * component fees are only applied here, and pack-container rows remain the
 * fee module's exclusive concern.
 */
final class PackCustomizationFeeCalculator
{
    private const TABLE = 'dydaps_customization_fee';

    public const AMOUNT_TYPE_TAX_EXCL = 'tax_excl';
    public const AMOUNT_TYPE_TAX_INCL = 'tax_incl';

    public const TAX_MODE_PRODUCT = 'product';
    public const TAX_MODE_NONE = 'none';
    public const TAX_MODE_SPECIFIC = 'specific';

    public const QUANTITY_PER_PRODUCT = 'per_product_quantity';
    public const QUANTITY_PER_CUSTOMIZATION_LINE = 'per_customization_line';

    private ?bool $tableAvailable = null;

    /**
     * Return whether the customization fee module table is installed.
     *
     * The module is an optional peer: when its table is absent the calculator
     * is a no-op and packs keep their component-sum pricing.
     *
     * @return bool true when the fee configuration table exists
     */
    public function isFeeModuleAvailable(): bool
    {
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        $tables = \Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . self::TABLE . '"');

        return $this->tableAvailable = is_array($tables) && count($tables) > 0;
    }

    /**
     * Return the shop-scoped fee configuration for one native customization field.
     *
     * @param int $idCustomizationField native customization field identifier
     * @param int $idShop shop identifier
     *
     * @return array<string, mixed>|null fee configuration row, or null when unavailable
     */
    public function getFeeConfig(int $idCustomizationField, int $idShop): ?array
    {
        if (!$this->isFeeModuleAvailable() || $idCustomizationField <= 0 || $idShop <= 0) {
            return null;
        }

        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE id_customization_field = ' . (int) $idCustomizationField . '
            AND id_shop = ' . (int) $idShop
        );

        if (!is_array($row)) {
            return null;
        }
        $row['label'] = json_decode((string) ($row['label'] ?? ''), true);

        return is_array($row['label']) ? $row : null;
    }

    /**
     * Compute per-field fee amounts for one component selection.
     *
     * @param list<array{id_customization_field: int, value: string}> $customizationFields submitted field values
     * @param int $idProduct component product identifier
     * @param int $idShop shop identifier
     * @param int $idCurrency currency used for fee amounts
     * @param int $idAddress tax address identifier, zero falls back to the default country
     * @param int $idLang language used for fee labels
     * @param int $componentQuantity component quantity per pack unit
     *
     * @return list<array{
     *     id_customization_field: int,
     *     label: string,
     *     enabled: bool,
     *     applied: bool,
     *     quantity_mode: string,
     *     amount_tax_excl: float,
     *     amount_tax_incl: float
     * }>
     */
    public function computeForFields(array $customizationFields, int $idProduct, int $idShop, int $idCurrency, int $idAddress, int $idLang, int $componentQuantity): array
    {
        $results = [];
        foreach ($customizationFields as $field) {
            $idField = (int) $field['id_customization_field'];
            if ($idField <= 0) {
                continue;
            }

            $config = $this->getFeeConfig($idField, $idShop);
            $value = trim((string) $field['value']);
            $chargeable = $this->isChargeable($config, $value);
            $results[] = [
                'id_customization_field' => $idField,
                'label' => $this->resolveLabel($config, $idLang),
                'enabled' => $this->isConfigured($config),
                'applied' => $chargeable,
                'quantity_mode' => (string) ($config['quantity_mode'] ?? self::QUANTITY_PER_PRODUCT),
                'amount_tax_excl' => $chargeable && $config !== null ? $this->computeUnitAmount($config, $idProduct, $idCurrency, $idAddress, false) : 0.0,
                'amount_tax_incl' => $chargeable && $config !== null ? $this->computeUnitAmount($config, $idProduct, $idCurrency, $idAddress, true) : 0.0,
            ];
        }

        return $results;
    }

    /**
     * Compute the total fee for one component selection.
     *
     * Quantity modes mirror the fee module: per-product fees multiply the
     * component quantity inside the pack, per-line fees are applied once.
     *
     * @param list<array{id_customization_field: int, value: string}> $customizationFields submitted field values
     * @param int $idProduct component product identifier
     * @param int $idShop shop identifier
     * @param int $idCurrency currency used for fee amounts
     * @param int $idAddress tax address identifier, zero falls back to the default country
     * @param int $idLang language used for fee labels
     * @param int $componentQuantity component quantity per pack unit
     *
     * @return array{0: float, 1: float} total tax-excluded and tax-included fee amounts
     */
    public function computeTotals(array $customizationFields, int $idProduct, int $idShop, int $idCurrency, int $idAddress, int $idLang, int $componentQuantity): array
    {
        $taxExcl = 0.0;
        $taxIncl = 0.0;
        foreach ($this->computeForFields($customizationFields, $idProduct, $idShop, $idCurrency, $idAddress, $idLang, $componentQuantity) as $fee) {
            if (!$fee['applied']) {
                continue;
            }
            $multiplier = $fee['quantity_mode'] === self::QUANTITY_PER_CUSTOMIZATION_LINE ? 1 : max(1, $componentQuantity);
            $taxExcl += $fee['amount_tax_excl'] * $multiplier;
            $taxIncl += $fee['amount_tax_incl'] * $multiplier;
        }

        return [round($taxExcl, 6), round($taxIncl, 6)];
    }

    /**
     * Compute tax-excluded and tax-included unit amounts for one fee config.
     *
     * Exposed for front-office display before any value is submitted; the
     * authoritative amounts used by pricing always go through computeTotals().
     *
     * @param array<string, mixed> $config fee configuration row
     * @param int $idProduct component product identifier
     * @param int $idCurrency target currency identifier
     * @param int $idAddress tax address identifier
     *
     * @return array{tax_excl: float, tax_incl: float} unit fee amounts
     */
    public function computeDisplayAmounts(array $config, int $idProduct, int $idCurrency, int $idAddress): array
    {
        return [
            'tax_excl' => $this->computeUnitAmount($config, $idProduct, $idCurrency, $idAddress, false),
            'tax_incl' => $this->computeUnitAmount($config, $idProduct, $idCurrency, $idAddress, true),
        ];
    }

    /**
     * Return whether a fee configuration is configured and enabled.
     *
     * @param array<string, mixed>|null $config fee configuration row
     *
     * @return bool true when the fee is enabled with a positive amount
     */
    public function isConfigured(?array $config): bool
    {
        return $config !== null && !empty($config['enabled']) && (float) ($config['amount'] ?? 0) > 0.0;
    }

    /**
     * Decide whether the fee applies to one submitted field value.
     *
     * @param array<string, mixed>|null $config fee configuration row
     * @param string $value submitted field value
     *
     * @return bool true when the fee is configured and either applies to empty values or the field is filled
     */
    public function isChargeable(?array $config, string $value): bool
    {
        if (!$this->isConfigured($config)) {
            return false;
        }

        return empty($config['apply_if_filled']) || $value !== '';
    }

    /**
     * Resolve the localized fee label stored by the fee module.
     *
     * @param array<string, mixed>|null $config fee configuration row
     * @param int $idLang language identifier
     *
     * @return string localized label, or an empty string when unavailable
     */
    public function resolveLabel(?array $config, int $idLang): string
    {
        if ($config === null || !is_array($config['label'] ?? null)) {
            return '';
        }
        $labels = $config['label'];
        foreach ([$idLang, array_key_first($labels)] as $candidate) {
            $label = trim((string) ($labels[$candidate] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    /**
     * Compute one tax-excluded or tax-included unit fee amount.
     *
     * The reference amount is converted from its stored currency, then taxed
     * according to the configured tax mode and amount type, exactly like the
     * fee module calculator.
     *
     * @param array<string, mixed> $config fee configuration row
     * @param int $idProduct component product identifier
     * @param int $idCurrency target currency identifier
     * @param int $idAddress tax address identifier
     * @param bool $taxIncl true to return a tax-included amount
     *
     * @return float unit fee amount in the requested tax mode
     */
    private function computeUnitAmount(array $config, int $idProduct, int $idCurrency, int $idAddress, bool $taxIncl): float
    {
        $amount = (float) ($config['amount'] ?? 0);
        if ($amount <= 0.0) {
            return 0.0;
        }

        $amount = $this->convertCurrency($amount, (int) ($config['id_currency'] ?? 0), $idCurrency);
        $calculator = $this->getTaxCalculator($config, $idProduct, $idAddress);
        $rate = (float) $calculator->getTotalRate();
        $storedTaxIncl = (string) ($config['amount_type'] ?? '') === self::AMOUNT_TYPE_TAX_INCL;

        if ($taxIncl) {
            if ($storedTaxIncl || $rate <= 0.0) {
                return $this->round($amount);
            }

            return $this->round((float) $calculator->addTaxes($amount));
        }

        if (!$storedTaxIncl || $rate <= 0.0) {
            return $this->round($amount);
        }

        return $this->round((float) $calculator->removeTaxes($amount));
    }

    /**
     * Build the tax calculator matching the configured fee tax mode.
     *
     * @param array<string, mixed> $config fee configuration row
     * @param int $idProduct component product identifier
     * @param int $idAddress tax address identifier
     *
     * @return \TaxCalculator fee tax calculator
     */
    private function getTaxCalculator(array $config, int $idProduct, int $idAddress): \TaxCalculator
    {
        if ((string) ($config['tax_mode'] ?? '') === self::TAX_MODE_NONE) {
            return new \TaxCalculator([]);
        }

        $idTaxRulesGroup = (int) ($config['id_tax_rules_group'] ?? 0);
        if ((string) ($config['tax_mode'] ?? '') === self::TAX_MODE_PRODUCT) {
            $product = new \Product($idProduct);
            $idTaxRulesGroup = (int) $product->id_tax_rules_group;
        }

        if ($idTaxRulesGroup <= 0) {
            return new \TaxCalculator([]);
        }

        $address = $idAddress > 0 ? new \Address($idAddress) : null;
        if (!$address || !\Validate::isLoadedObject($address)) {
            $address = new \Address();
            $address->id_country = (int) \Configuration::get('PS_COUNTRY_DEFAULT');
        }

        return \TaxManagerFactory::getManager($address, $idTaxRulesGroup)->getTaxCalculator();
    }

    /**
     * Convert a reference amount between currencies.
     *
     * @param float $amount reference amount
     * @param int $fromCurrency source currency identifier
     * @param int $toCurrency target currency identifier
     *
     * @return float converted amount
     */
    private function convertCurrency(float $amount, int $fromCurrency, int $toCurrency): float
    {
        if ($fromCurrency <= 0 || $toCurrency <= 0 || $fromCurrency === $toCurrency) {
            return $amount;
        }

        $from = \Currency::getCurrencyInstance($fromCurrency);
        $to = \Currency::getCurrencyInstance($toCurrency);
        if (!\Validate::isLoadedObject($from) || !\Validate::isLoadedObject($to)) {
            return $amount;
        }

        return (float) \Tools::convertPriceFull($amount, $from, $to);
    }

    /**
     * Apply PrestaShop price precision to a computed amount.
     *
     * @param float $amount raw computed amount
     *
     * @return float rounded amount
     */
    private function round(float $amount): float
    {
        return (float) \Tools::ps_round($amount, (int) \Configuration::get('PS_PRICE_DISPLAY_PRECISION'));
    }
}
