<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Centralizes PrestaShop version capabilities used by configurable packs.
 */
final class PrestaShopCompatibilityService
{
    /**
     * First PrestaShop version where Product::priceCalculation exposes the
     * actionProductPriceCalculation hook needed for per-customization pricing.
     */
    private const MIN_PRICE_HOOK_VERSION = '8.1.0';

    /**
     * Highest major version range accepted by the module metadata.
     */
    private const MAX_SUPPORTED_VERSION = '9.99.999';

    /**
     * Return whether native product price calculations can be overridden safely.
     *
     * @return bool True when actionProductPriceCalculation is available.
     */
    public function supportsProductPriceCalculationHook(): bool
    {
        return version_compare($this->version(), self::MIN_PRICE_HOOK_VERSION, '>=');
    }

    /**
     * Return module metadata compliancy for the fully supported feature set.
     *
     * @return array{min: string, max: string}
     */
    public function getModuleCompliancy(): array
    {
        return [
            'min' => self::MIN_PRICE_HOOK_VERSION,
            'max' => self::MAX_SUPPORTED_VERSION,
        ];
    }

    /**
     * Return the active PrestaShop version.
     *
     * @return string Core version string.
     */
    private function version(): string
    {
        return defined('_PS_VERSION_') ? (string) _PS_VERSION_ : '0.0.0';
    }
}
