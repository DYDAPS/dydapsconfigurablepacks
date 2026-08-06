<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Grid\Definition\Factory;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Defines the back-office grid used to list configurable pack definitions.
 */
final class PackGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    /**
     * Stable grid identifier used for filters and reset routing.
     */
    public const GRID_ID = 'dydaps_configurable_packs';

    /**
     * Return the stable PrestaShop grid identifier.
     *
     * @return string Grid identifier.
     */
    protected function getId(): string
    {
        return self::GRID_ID;
    }

    /**
     * Return the translated grid title.
     *
     * @return string Translated grid name.
     */
    protected function getName(): string
    {
        return $this->trans('Configurable Packs', [], 'Modules.Dydapsconfigurablepacks.Admin');
    }

    /**
     * Define displayed pack columns and row actions.
     *
     * @return ColumnCollection Configured grid columns.
     */
    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add((new DataColumn('id_pack'))->setName($this->trans('ID', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'id_pack']))
            ->add((new DataColumn('image'))->setName($this->trans('Image', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'image']))
            ->add((new DataColumn('name'))->setName($this->trans('Name', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'name']))
            ->add((new DataColumn('reference'))->setName($this->trans('Reference', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'reference']))
            ->add((new DataColumn('shop_name'))->setName($this->trans('Shop', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'shop_name']))
            ->add((new DataColumn('pack_type'))->setName($this->trans('Pack type', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'pack_type']))
            ->add((new DataColumn('component_count'))->setName($this->trans('Components', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'component_count']))
            ->add((new DataColumn('pricing_method'))->setName($this->trans('Pricing method', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'pricing_method']))
            ->add((new DataColumn('price'))->setName($this->trans('Price', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'price']))
            ->add((new DataColumn('availability'))->setName($this->trans('Availability', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'availability']))
            ->add((new DataColumn('active'))->setName($this->trans('Status', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'active']))
            ->add((new DataColumn('updated_at'))->setName($this->trans('Last update', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'updated_at']))
            ->add((new ActionColumn('actions'))->setName($this->trans('Actions', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['actions' => $this->getRowActions()]));
    }

    /**
     * Define edit, toggle and delete actions for each pack row.
     *
     * @return RowActionCollection Configured row actions.
     */
    private function getRowActions(): RowActionCollection
    {
        return (new RowActionCollection())
            ->add((new LinkRowAction('edit'))->setName($this->trans('Edit', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setIcon('edit')->setOptions(['route' => 'dydaps_configurable_packs_edit', 'route_param_name' => 'id', 'route_param_field' => 'id_pack']))
            ->add((new SubmitRowAction('toggle'))->setName($this->trans('Enable / disable', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setIcon('power_settings_new')->setOptions(['route' => 'dydaps_configurable_packs_toggle', 'route_param_name' => 'id', 'route_param_field' => 'id_pack']))
            ->add((new SubmitRowAction('delete'))->setName($this->trans('Delete configuration', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setIcon('delete')->setOptions(['route' => 'dydaps_configurable_packs_delete', 'route_param_name' => 'id', 'route_param_field' => 'id_pack', 'confirm_message' => $this->trans('Delete this pack configuration?', [], 'Modules.Dydapsconfigurablepacks.Admin')]));
    }

    /**
     * Define grid filters mapped to PackQueryBuilder fields.
     *
     * @return FilterCollection Configured grid filters.
     */
    protected function getFilters(): FilterCollection
    {
        return (new FilterCollection())
            ->add((new Filter('id_pack', TextType::class))->setAssociatedColumn('id_pack')->setTypeOptions(['required' => false]))
            ->add((new Filter('search', TextType::class))->setAssociatedColumn('name')->setTypeOptions(['required' => false, 'attr' => ['placeholder' => $this->trans('Search by name or reference', [], 'Modules.Dydapsconfigurablepacks.Admin')]]))
            ->add((new Filter('pricing_method', ChoiceType::class))->setAssociatedColumn('pricing_method')->setTypeOptions(['required' => false, 'choices' => ['Fixed price' => 'fixed', 'Component sum' => 'component_sum', 'Percentage discount' => 'percent_discount', 'Fixed discount' => 'fixed_discount', 'Forced price' => 'forced']]))
            ->add((new Filter('active', YesAndNoChoiceType::class))->setAssociatedColumn('active')->setTypeOptions(['required' => false]))
            ->add((new Filter('actions', SearchAndResetType::class))->setAssociatedColumn('actions')->setTypeOptions(['reset_route' => 'admin_common_reset_search_by_filter_id', 'reset_route_params' => ['filterId' => self::GRID_ID], 'redirect_route' => 'dydaps_configurable_packs_index']));
    }
}
