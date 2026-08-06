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

final class PackConfig
{
    public const KEY_DELETE_DATA = 'DYDAPS_CONFIGURABLE_PACKS_DELETE_DATA';
    public const KEY_PRICE_ROUND_PRECISION = 'DYDAPS_CONFIGURABLE_PACKS_ROUND_PRECISION';
    public const PRICING_FIXED = 'fixed';
    public const PRICING_COMPONENT_SUM = 'component_sum';
    public const PRICING_PERCENT_DISCOUNT = 'percent_discount';
    public const PRICING_FIXED_DISCOUNT = 'fixed_discount';
    public const PRICING_FORCED = 'forced';
}
