<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Grid\Query;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Builds Doctrine DBAL queries for the configurable pack admin grid.
 */
final class PackQueryBuilder extends AbstractDoctrineQueryBuilder
{
    private DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator;
    private LegacyContext $legacyContext;

    /**
     * @param Connection $connection Doctrine database connection.
     * @param string $dbPrefix PrestaShop database table prefix.
     * @param DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator Applicator for grid sorting and pagination.
     * @param LegacyContext $legacyContext Legacy PrestaShop context adapter.
     *
     * @return void
     */
    public function __construct(Connection $connection, string $dbPrefix, DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator, LegacyContext $legacyContext)
    {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
        $this->legacyContext = $legacyContext;
    }

    /**
     * Build the paginated search query for grid rows.
     *
     * @param SearchCriteriaInterface $searchCriteria Grid search criteria.
     *
     * @return QueryBuilder Query returning displayed grid rows.
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder()
            ->select('pk.id_pack', '"-" AS image', 'pl.name', 'p.reference', 's.name AS shop_name', 'pk.pack_type', 'COUNT(c.id_component) AS component_count', 'pk.pricing_method', 'pk.fixed_price_tax_excl AS price', 'pk.stock_behavior AS availability', 'pk.active', 'pk.updated_at')
            ->groupBy('pk.id_pack');
        $filters = $searchCriteria->getFilters();
        $this->applyFilters($qb, is_array($filters) ? $filters : []);
        $this->searchCriteriaApplicator->applySorting($searchCriteria, $qb)->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    /**
     * Build the count query used by grid pagination.
     *
     * @param SearchCriteriaInterface $searchCriteria Grid search criteria.
     *
     * @return QueryBuilder Query returning the total matching row count.
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder()->select('COUNT(DISTINCT pk.id_pack)');
        $filters = $searchCriteria->getFilters();
        $this->applyFilters($qb, is_array($filters) ? $filters : []);

        return $qb;
    }

    /**
     * Build the shared base query scoped to non-deleted pack definitions.
     *
     * @return QueryBuilder Base pack grid query.
     */
    private function getBaseQueryBuilder(): QueryBuilder
    {
        $context = $this->legacyContext->getContext();
        $idLang = (int) $context->language->id;

        return $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'dydaps_pack', 'pk')
            ->leftJoin('pk', $this->dbPrefix . 'product', 'p', 'p.id_product = pk.id_product')
            ->leftJoin('pk', $this->dbPrefix . 'product_lang', 'pl', 'pl.id_product = pk.id_product AND pl.id_lang = :id_lang AND pl.id_shop = pk.id_shop')
            ->leftJoin('pk', $this->dbPrefix . 'shop', 's', 's.id_shop = pk.id_shop')
            ->leftJoin('pk', $this->dbPrefix . 'dydaps_pack_component', 'c', 'c.id_pack = pk.id_pack')
            ->where('pk.deleted_at IS NULL')
            ->setParameter('id_lang', $idLang);
    }

    /**
     * Apply supported grid filters to a query builder.
     *
     * @param QueryBuilder $qb Query builder to mutate.
     * @param array<string, mixed> $filters
     *
     * @return void
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['id_pack'])) {
            $qb->andWhere('pk.id_pack = :id_pack')->setParameter('id_pack', (int) $filters['id_pack']);
        }
        if (!empty($filters['search']) && is_string($filters['search'])) {
            $qb->andWhere('(pl.name LIKE :search OR p.reference LIKE :search)')->setParameter('search', '%' . trim($filters['search']) . '%');
        }
        if (isset($filters['pricing_method']) && $filters['pricing_method'] !== '') {
            $qb->andWhere('pk.pricing_method = :pricing_method')->setParameter('pricing_method', (string) $filters['pricing_method']);
        }
        if (isset($filters['active']) && $filters['active'] !== '') {
            $qb->andWhere('pk.active = :active')->setParameter('active', (int) $filters['active']);
        }
    }
}
