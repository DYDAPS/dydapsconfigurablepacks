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

namespace Dydaps\ConfigurablePacks\Grid\Definition\Factory;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Config\PackConfig;
use Dydaps\ConfigurablePacks\Grid\Column\Type\PriceColumn;
use Dydaps\ConfigurablePacks\Grid\Column\Type\TranslatedBadgeColumn;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollectionInterface;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use PrestaShopBundle\Form\Admin\Type\YesAndNoChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Defines the back-office grid used to list configurable pack definitions.
 */
final class PackGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    /**
     * Stable grid identifier used for filters and reset routing.
     */
    public const GRID_ID = 'dydaps_configurable_packs';

    private const TOKEN_TOGGLE = 'dydaps.configurable_packs.pack.toggle';
    private const TOKEN_DELETE = 'dydaps.configurable_packs.pack.delete';

    private ?CsrfTokenManagerInterface $csrfTokenManager = null;

    /**
     * Inject the CSRF token manager used to protect row action routes.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager security token manager
     *
     * @return void
     */
    public function setCsrfTokenManager(CsrfTokenManagerInterface $csrfTokenManager): void
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Return the stable PrestaShop grid identifier.
     *
     * @return string grid identifier
     */
    protected function getId(): string
    {
        return self::GRID_ID;
    }

    /**
     * Return the translated grid title.
     *
     * @return string translated grid name
     */
    protected function getName(): string
    {
        return $this->trans('Configurable Packs', [], 'Modules.Dydapsconfigurablepacks.Admin');
    }

    /**
     * Define displayed pack columns and row actions.
     *
     * @return ColumnCollection configured grid columns
     */
    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add((new DataColumn('id_pack'))->setName($this->trans('ID', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'id_pack']))
            ->add((new DataColumn('name'))->setName($this->trans('Name', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'name']))
            ->add((new DataColumn('reference'))->setName($this->trans('Reference', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'reference']))
            ->add((new DataColumn('shop_name'))->setName($this->trans('Shop', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'shop_name']))
            ->add($this->createPackTypeColumn())
            ->add((new DataColumn('component_count'))->setName($this->trans('Components', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'component_count']))
            ->add($this->createPricingMethodColumn())
            ->add((new PriceColumn('price'))->setName($this->trans('Price', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'price', 'decimals' => 2, 'empty_value' => '-']))
            ->add((new DataColumn('availability'))->setName($this->trans('Availability', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'availability']))
            ->add($this->createStatusColumn())
            ->add((new DataColumn('updated_at'))->setName($this->trans('Last update', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['field' => 'updated_at']))
            ->add((new ActionColumn('actions'))->setName($this->trans('Actions', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setOptions(['actions' => $this->getRowActions()]));
    }

    /**
     * Create the pack type badge column.
     *
     * @return TranslatedBadgeColumn pack type column
     */
    private function createPackTypeColumn(): TranslatedBadgeColumn
    {
        $column = new TranslatedBadgeColumn('pack_type');
        $column->setName($this->trans('Pack type', [], 'Modules.Dydapsconfigurablepacks.Admin'));
        $column->setOptions([
            'field' => 'pack_type',
            'labels' => [
                'fixed' => $this->trans('Fixed components', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                'choice' => $this->trans('Choice groups', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                'steps' => $this->trans('Steps', [], 'Modules.Dydapsconfigurablepacks.Admin'),
            ],
            'badge_types' => [
                'fixed' => 'primary',
                'choice' => 'info',
                'steps' => 'warning',
            ],
            'default_badge_type' => 'info',
        ]);

        return $column;
    }

    /**
     * Create the pricing method badge column.
     *
     * @return TranslatedBadgeColumn pricing method column
     */
    private function createPricingMethodColumn(): TranslatedBadgeColumn
    {
        $column = new TranslatedBadgeColumn('pricing_method');
        $column->setName($this->trans('Pricing method', [], 'Modules.Dydapsconfigurablepacks.Admin'));
        $column->setOptions([
            'field' => 'pricing_method',
            'labels' => [
                PackConfig::PRICING_FIXED => $this->trans('Fixed price', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                PackConfig::PRICING_COMPONENT_SUM => $this->trans('Component sum', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                PackConfig::PRICING_PERCENT_DISCOUNT => $this->trans('Percentage discount', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                PackConfig::PRICING_FIXED_DISCOUNT => $this->trans('Fixed discount', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                PackConfig::PRICING_FORCED => $this->trans('Forced price', [], 'Modules.Dydapsconfigurablepacks.Admin'),
            ],
            'badge_types' => [
                PackConfig::PRICING_FIXED => 'success',
                PackConfig::PRICING_COMPONENT_SUM => 'info',
                PackConfig::PRICING_PERCENT_DISCOUNT => 'warning',
                PackConfig::PRICING_FIXED_DISCOUNT => 'warning',
                PackConfig::PRICING_FORCED => 'danger',
            ],
            'default_badge_type' => 'info',
        ]);

        return $column;
    }

    /**
     * Create the activation status badge column.
     *
     * @return TranslatedBadgeColumn status column
     */
    private function createStatusColumn(): TranslatedBadgeColumn
    {
        $column = new TranslatedBadgeColumn('active');
        $column->setName($this->trans('Status', [], 'Modules.Dydapsconfigurablepacks.Admin'));
        $column->setOptions([
            'field' => 'active',
            'labels' => [
                0 => $this->trans('Inactive', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                1 => $this->trans('Active', [], 'Modules.Dydapsconfigurablepacks.Admin'),
            ],
            'badge_types' => [
                0 => 'danger',
                1 => 'success',
            ],
            'default_badge_type' => 'secondary',
            'empty_value' => '-',
        ]);

        return $column;
    }

    /**
     * Define edit, toggle and delete actions for each pack row.
     *
     * @return RowActionCollectionInterface configured row actions
     */
    private function getRowActions(): RowActionCollectionInterface
    {
        return (new RowActionCollection())
            ->add((new LinkRowAction('edit'))->setName($this->trans('Edit', [], 'Modules.Dydapsconfigurablepacks.Admin'))->setIcon('edit')->setOptions(['route' => 'dydaps_configurable_packs_edit', 'route_param_name' => 'id', 'route_param_field' => 'id_pack']))
            ->add($this->createSubmitAction('toggle', 'Enable / disable', 'power_settings_new', 'dydaps_configurable_packs_toggle', self::TOKEN_TOGGLE, 'Change this pack status?'))
            ->add($this->createSubmitAction('delete', 'Delete configuration', 'delete', 'dydaps_configurable_packs_delete', self::TOKEN_DELETE, 'Delete this pack configuration?'));
    }

    /**
     * Build a protected row action submitting the targeted route with a CSRF token.
     *
     * @param string $name action identifier
     * @param string $label translated action label
     * @param string $icon material icon name
     * @param string $route action route
     * @param string $tokenId CSRF token identifier
     * @param string $confirmation translated confirmation message
     *
     * @return SubmitRowAction configured row action
     */
    private function createSubmitAction(string $name, string $label, string $icon, string $route, string $tokenId, string $confirmation): SubmitRowAction
    {
        if ($this->csrfTokenManager === null) {
            throw new \RuntimeException('CSRF token manager is not configured for pack grid actions.');
        }

        $action = new SubmitRowAction($name);
        $action
            ->setName($this->trans($label, [], 'Modules.Dydapsconfigurablepacks.Admin'))
            ->setIcon($icon)
            ->setOptions([
                'route' => $route,
                'route_param_name' => 'id',
                'route_param_field' => 'id_pack',
                'method' => 'POST',
                'confirm_message' => $this->trans($confirmation, [], 'Modules.Dydapsconfigurablepacks.Admin'),
                'extra_route_params' => ['_csrf_token' => (string) $this->csrfTokenManager->getToken($tokenId)],
            ]);

        return $action;
    }

    /**
     * Define grid filters mapped to PackQueryBuilder fields.
     *
     * @return FilterCollectionInterface configured grid filters
     */
    protected function getFilters(): FilterCollectionInterface
    {
        return (new FilterCollection())
            ->add((new Filter('id_pack', TextType::class))->setAssociatedColumn('id_pack')->setTypeOptions(['required' => false]))
            ->add((new Filter('search', TextType::class))->setAssociatedColumn('name')->setTypeOptions(['required' => false, 'attr' => ['placeholder' => $this->trans('Search by name or reference', [], 'Modules.Dydapsconfigurablepacks.Admin')]]))
            ->add((new Filter('pricing_method', ChoiceType::class))->setAssociatedColumn('pricing_method')->setTypeOptions([
                'required' => false,
                'placeholder' => $this->trans('All', [], 'Modules.Dydapsconfigurablepacks.Admin'),
                'choices' => [
                    $this->trans('Fixed price', [], 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FIXED,
                    $this->trans('Component sum', [], 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_COMPONENT_SUM,
                    $this->trans('Percentage discount', [], 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_PERCENT_DISCOUNT,
                    $this->trans('Fixed discount', [], 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FIXED_DISCOUNT,
                    $this->trans('Forced price', [], 'Modules.Dydapsconfigurablepacks.Admin') => PackConfig::PRICING_FORCED,
                ],
            ]))
            ->add((new Filter('active', YesAndNoChoiceType::class))->setAssociatedColumn('active')->setTypeOptions(['required' => false]))
            ->add((new Filter('actions', SearchAndResetType::class))->setAssociatedColumn('actions')->setTypeOptions(['reset_route' => 'admin_common_reset_search_by_filter_id', 'reset_route_params' => ['filterId' => self::GRID_ID], 'redirect_route' => 'dydaps_configurable_packs_index']));
    }
}
