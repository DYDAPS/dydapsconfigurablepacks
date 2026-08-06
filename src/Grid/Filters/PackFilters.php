<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Grid\Filters;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Search\Filters;

final class PackFilters extends Filters
{
    protected $filterId = 'dydaps_configurable_packs';

    public static function getDefaults(): array
    {
        return [
            'limit' => 50,
            'offset' => 0,
            'orderBy' => 'id_pack',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}
