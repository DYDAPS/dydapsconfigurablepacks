<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Grid\Filters;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Search\Filters;

/**
 * Stores filter, pagination and sorting defaults for the pack grid.
 */
final class PackFilters extends Filters
{
    /**
     * Grid identifier expected by PrestaShop search persistence.
     *
     * @var string
     */
    protected $filterId = 'dydaps_configurable_packs';

    /**
     * @param array<string, mixed> $parameters Grid parameters provided by the request.
     */
    public function __construct(array $parameters = [])
    {
        parent::__construct(array_merge(self::getDefaults(), $parameters));
    }

    /**
     * Return default search criteria for the pack grid.
     *
     * @return array{
     *     limit: int,
     *     offset: int,
     *     orderBy: string,
     *     sortOrder: string,
     *     filters: array<string, mixed>
     * }
     */
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
