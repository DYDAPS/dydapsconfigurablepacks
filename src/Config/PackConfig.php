<?php
/**
 * 2007-2026 PrestaShop SA and Contributors
 *
 * @author    DYDAPS
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Centralizes module configuration keys and pack pricing strategy identifiers.
 */
final class PackConfig
{
    /**
     * Boolean configuration flag controlling whether uninstall removes module data.
     */
    public const KEY_DELETE_DATA = 'DYDAPS_CONFIGURABLE_PACKS_DELETE_DATA';

    /**
     * Decimal precision used for persisted calculated prices.
     */
    public const KEY_PRICE_ROUND_PRECISION = 'DYDAPS_CONFIGURABLE_PACKS_ROUND_PRECISION';

    /**
     * Pricing strategy where the pack uses a configured tax-excluded fixed price.
     */
    public const PRICING_FIXED = 'fixed';

    /**
     * Pricing strategy where the pack price is the sum of selected components.
     */
    public const PRICING_COMPONENT_SUM = 'component_sum';

    /**
     * Pricing strategy applying a percentage discount to the component sum.
     */
    public const PRICING_PERCENT_DISCOUNT = 'percent_discount';

    /**
     * Pricing strategy applying a fixed tax-excluded discount to the component sum.
     */
    public const PRICING_FIXED_DISCOUNT = 'fixed_discount';

    /**
     * Pricing strategy where the final tax-excluded pack price is forced.
     */
    public const PRICING_FORCED = 'forced';
}
