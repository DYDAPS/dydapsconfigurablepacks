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

final class PackQueryBuilder extends AbstractDoctrineQueryBuilder
{
    private DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator;
    private LegacyContext $legacyContext;

    public function __construct(Connection $connection, string $dbPrefix, DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator, LegacyContext $legacyContext)
    {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
        $this->legacyContext = $legacyContext;
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder()
            ->select('pk.id_pack', '"-" AS image', 'pl.name', 'p.reference', 's.name AS shop_name', 'pk.pack_type', 'COUNT(c.id_component) AS component_count', 'pk.pricing_method', 'pk.fixed_price_tax_excl AS price', 'pk.stock_behavior AS availability', 'pk.active', 'pk.updated_at')
            ->groupBy('pk.id_pack');
        $this->applyFilters($qb, $searchCriteria->getFilters());
        $this->searchCriteriaApplicator->applySorting($searchCriteria, $qb)->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder()->select('COUNT(DISTINCT pk.id_pack)');
        $this->applyFilters($qb, $searchCriteria->getFilters());

        return $qb;
    }

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
