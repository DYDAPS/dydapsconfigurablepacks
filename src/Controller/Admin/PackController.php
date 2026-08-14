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

namespace Dydaps\ConfigurablePacks\Controller\Admin;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Config\PackConfig;
use Dydaps\ConfigurablePacks\Form\PackGeneralType;
use Dydaps\ConfigurablePacks\Grid\Definition\Factory\PackGridDefinitionFactory;
use Dydaps\ConfigurablePacks\Grid\Filters\PackFilters;
use Dydaps\ConfigurablePacks\Repository\PackRepository;
use Dydaps\ConfigurablePacks\Service\PackProductService;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office controller for configurable pack administration.
 *
 * It lists pack definitions in a PrestaShop grid and handles the create/edit
 * actions for pack metadata, catalog content, shipping, pricing, SEO settings
 * and the component composition.
 */
final class PackController extends AbstractDydapsAdminController
{
    private GridFactoryInterface $gridFactory;
    private PackRepository $repository;
    private PackProductService $productService;

    /**
     * @param GridFactoryInterface $gridFactory factory building the pack grid
     * @param GridPresenterInterface $gridPresenter presenter converting grids to template data
     * @param PackRepository $repository repository used for pack persistence
     * @param PackProductService $productService service managing the native pack product
     * @param object|null $translator prestaShop translator-like service
     *
     * @return void
     */
    public function __construct(GridFactoryInterface $gridFactory, GridPresenterInterface $gridPresenter, PackRepository $repository, PackProductService $productService, $translator)
    {
        $this->gridFactory = $gridFactory;
        $this->setGridPresenter($gridPresenter);
        $this->repository = $repository;
        $this->productService = $productService;
        $this->setTranslator($translator);
    }

    /**
     * Display the pack grid.
     *
     * @param Request $request current admin request
     *
     * @return Response rendered pack grid page
     */
    public function index(Request $request): Response
    {
        $this->denyRead($request);
        $gridQuery = $this->getArrayFromBag($request, 'query', PackGridDefinitionFactory::GRID_ID);
        $filters = new PackFilters($gridQuery);
        $grid = $this->gridFactory->getGrid($filters);

        return $this->render('@Modules/dydapsconfigurablepacks/views/templates/admin/packs.html.twig', [
            'layoutTitle' => $this->t('Configurable Packs', 'Modules.Dydapsconfigurablepacks.Admin'),
            'active' => 'packs',
            'grid' => $this->presentGrid($grid),
            'permissions' => $this->getAdminPermissions($request),
        ]);
    }

    /**
     * Convert submitted grid filters into the canonical grid query parameters.
     *
     * @param Request $request current admin request
     *
     * @return RedirectResponse redirect to the filtered grid
     */
    public function search(Request $request): RedirectResponse
    {
        $this->denyRead($request);

        $payload = $this->getArrayFromBag($request, 'request', PackGridDefinitionFactory::GRID_ID);
        $filters = $payload['filters'] ?? $payload;
        $filters = is_array($filters) ? $filters : [];
        unset($filters['_token'], $filters['actions']);

        return $this->redirectToRoute('dydaps_configurable_packs_index', [
            PackGridDefinitionFactory::GRID_ID => [
                'filters' => [
                    'id_pack' => isset($filters['id_pack']) ? (string) $filters['id_pack'] : '',
                    'search' => isset($filters['search']) ? (string) $filters['search'] : '',
                    'pricing_method' => isset($filters['pricing_method']) ? (string) $filters['pricing_method'] : '',
                    'active' => isset($filters['active']) ? (string) $filters['active'] : '',
                ],
            ],
        ]);
    }

    /**
     * Display and process the pack creation form.
     *
     * @param Request $request current admin request
     *
     * @return Response rendered form or redirect response after save
     */
    public function create(Request $request): Response
    {
        $this->denyCreate($request);

        return $this->handleForm($request, null);
    }

    /**
     * Display and process the pack edit form.
     *
     * @param Request $request current admin request
     * @param int $id pack identifier
     *
     * @return Response rendered form or redirect response after save
     */
    public function edit(Request $request, int $id): Response
    {
        $this->denyUpdate($request);
        $pack = $this->repository->getPack($id);
        if (!$pack) {
            $this->addFlash('error', $this->t('Pack configuration not found.', 'Modules.Dydapsconfigurablepacks.Admin'));

            return $this->redirectToRoute('dydaps_configurable_packs_index');
        }

        return $this->handleForm($request, $pack);
    }

    /**
     * Toggle a pack definition between enabled and disabled states.
     *
     * @param Request $request current admin request
     * @param int $id pack identifier
     *
     * @return RedirectResponse redirect back to the pack grid
     */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->denyUpdate($request);
        if (!$this->validateActionCsrf($request, 'dydaps.configurable_packs.pack.toggle')) {
            return $this->redirectToRoute('dydaps_configurable_packs_index');
        }
        $pack = $this->repository->getPack($id);
        if ($pack) {
            \Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'dydaps_pack` SET active = IF(active = 1, 0, 1), updated_at = NOW() WHERE id_pack = ' . (int) $id);
            $this->addFlash('success', $this->t('Pack status updated.', 'Modules.Dydapsconfigurablepacks.Admin'));
        }

        return $this->redirectToRoute('dydaps_configurable_packs_index');
    }

    /**
     * Soft-delete a pack definition.
     *
     * @param Request $request current admin request
     * @param int $id pack identifier
     *
     * @return RedirectResponse redirect back to the pack grid
     */
    public function delete(Request $request, int $id): RedirectResponse
    {
        $this->denyDelete($request);
        if (!$this->validateActionCsrf($request, 'dydaps.configurable_packs.pack.delete')) {
            return $this->redirectToRoute('dydaps_configurable_packs_index');
        }
        $this->repository->deletePack($id);
        $this->addFlash('success', $this->t('Pack configuration deleted.', 'Modules.Dydapsconfigurablepacks.Admin'));

        return $this->redirectToRoute('dydaps_configurable_packs_index');
    }

    /**
     * Search products and combinations available to the current shop.
     *
     * @param Request $request current admin request
     *
     * @return JsonResponse product choices usable by the component builder
     */
    public function productSearch(Request $request): JsonResponse
    {
        $this->denyRead($request);

        $query = trim((string) $request->query->get('q', ''));
        if (\Tools::strlen($query) < 2) {
            return new JsonResponse(['ok' => true, 'products' => []]);
        }

        return new JsonResponse([
            'ok' => true,
            'products' => $this->repository->searchProductsForBuilder(
                $query,
                (int) $this->getContext()->shop->id,
                (int) $this->getContext()->language->id
            ),
        ]);
    }

    /**
     * Render and process the general pack form.
     *
     * @param Request $request current admin request
     * @param array<string, mixed>|null $pack existing pack row, or null for creation defaults
     *
     * @return Response rendered form or redirect response after save
     */
    private function handleForm(Request $request, ?array $pack): Response
    {
        $idShop = (int) ($pack['id_shop'] ?? $this->getContext()->shop->id);
        $idLang = (int) $this->getContext()->language->id;
        $idProduct = (int) ($pack['id_product'] ?? 0);

        $data = $this->buildFormData($pack, $idProduct, $idShop, $idLang);
        $form = $this->createForm(PackGeneralType::class, $data, [
            'category_choices' => $this->getCategoryChoicesForForm($idLang, array_map('intval', (array) ($data['categories'] ?? []))),
            'tax_rule_choices' => $this->getTaxRuleChoicesForForm(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $payload = $form->getData();
            $payload['id_pack'] = (int) ($pack['id_pack'] ?? 0);
            $payload['id_shop'] = $idShop;
            $payload['active'] = (int) ($pack['active'] ?? 1);

            $validationError = $this->validatePayload($payload);
            if ($validationError !== '') {
                $this->addFlash('error', $validationError);

                return $this->redirectToRoute($pack ? 'dydaps_configurable_packs_edit' : 'dydaps_configurable_packs_create', $pack ? ['id' => (int) $pack['id_pack']] : []);
            }
            $selectedCategories = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['categories'] ?? [])))));
            if ((int) ($payload['default_category'] ?? 0) <= 0 && $selectedCategories) {
                $payload['default_category'] = $selectedCategories[0];
            }

            $components = json_decode((string) ($payload['components_json'] ?? '[]'), true);
            if (!is_array($components)) {
                $this->addFlash('error', $this->t('The pack component definition is invalid.', 'Modules.Dydapsconfigurablepacks.Admin'));

                return $this->redirectToRoute($pack ? 'dydaps_configurable_packs_edit' : 'dydaps_configurable_packs_create', $pack ? ['id' => (int) $pack['id_pack']] : []);
            }
            $errors = $this->validateComponentsForSave($components);
            if ($errors) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->redirectToRoute($pack ? 'dydaps_configurable_packs_edit' : 'dydaps_configurable_packs_create', $pack ? ['id' => (int) $pack['id_pack']] : []);
            }

            $idProduct = (int) ($payload['id_product'] ?? 0);
            if ($idProduct > 0) {
                $existingPack = $this->repository->getPackByProduct($idProduct, $idShop);
                if ($existingPack && (int) $existingPack['id_pack'] !== (int) $payload['id_pack']) {
                    $this->addFlash('error', $this->t('This product is already configured as a pack for the current shop.', 'Modules.Dydapsconfigurablepacks.Admin'));

                    return $this->redirectToRoute($pack ? 'dydaps_configurable_packs_edit' : 'dydaps_configurable_packs_create', $pack ? ['id' => (int) $pack['id_pack']] : []);
                }
            }

            try {
                $idProduct = $this->productService->createOrUpdate($idProduct > 0 ? $idProduct : null, $payload, $idShop);
            } catch (\Throwable $e) {
                $this->addFlash('error', $this->t('Unable to save the pack product.', 'Modules.Dydapsconfigurablepacks.Admin'));

                return $this->redirectToRoute($pack ? 'dydaps_configurable_packs_edit' : 'dydaps_configurable_packs_create', $pack ? ['id' => (int) $pack['id_pack']] : []);
            }
            $payload['id_product'] = $idProduct;

            $coverFile = $form->get('cover_image')->getData();
            if ($coverFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                try {
                    $this->productService->setCoverImage($idProduct, $coverFile, $idShop);
                } catch (\Throwable $e) {
                    $this->addFlash('error', $this->t('Unable to upload the pack cover image.', 'Modules.Dydapsconfigurablepacks.Admin'));
                }
            }

            unset($payload['components_json']);
            $idPack = $this->repository->savePack($payload);
            $this->repository->replaceComponents($idPack, array_values($components), $idLang);
            $this->addFlash('success', $this->t('Pack configuration saved.', 'Modules.Dydapsconfigurablepacks.Admin'));

            return $this->redirectToRoute('dydaps_configurable_packs_index');
        }

        $currentData = is_array($form->getData()) ? $form->getData() : $data;
        $selectedCategories = array_values(array_unique(array_filter(array_map('intval', (array) ($currentData['categories'] ?? [])))));
        $selectedAccessories = $this->getSelectedAccessories((array) ($currentData['accessories'] ?? []), $idLang);

        $coverImageUrl = '';
        $hasCoverImage = false;
        if ($idProduct > 0) {
            $cover = \Product::getCover($idProduct);
            if (is_array($cover) && isset($cover['id_image'])) {
                $coverImageUrl = (string) $this->getContext()->link->getImageLink('dydaps-pack-cover-' . $idProduct, (int) $cover['id_image'], 'home_default');
                $hasCoverImage = true;
            }
        }

        return $this->render('@Modules/dydapsconfigurablepacks/views/templates/admin/pack_form.html.twig', [
            'layoutTitle' => $pack ? $this->t('Edit pack', 'Modules.Dydapsconfigurablepacks.Admin') : $this->t('Create pack', 'Modules.Dydapsconfigurablepacks.Admin'),
            'active' => 'packs',
            'form' => $form->createView(),
            'canUpdate' => true,
            'permissions' => $this->getAdminPermissions($request),
            'taxRates' => $this->getTaxRateMap(),
            'categoryTree' => $this->getCategoryTree($idLang),
            'selectedCategories' => $selectedCategories,
            'selectedAccessories' => $selectedAccessories,
            'coverImageUrl' => $coverImageUrl,
            'hasCoverImage' => $hasCoverImage,
            'defaultLocaleId' => (int) $this->getContext()->language->id,
            'productSearchUrl' => $this->generateUrl('dydaps_configurable_packs_product_search'),
        ]);
    }

    /**
     * Build the form payload from an existing pack and its linked product.
     *
     * @param array<string, mixed>|null $pack existing pack row, or null for creation defaults
     * @param int $idProduct linked native product identifier
     * @param int $idShop pack shop identifier
     * @param int $idLang current language identifier
     *
     * @return array<string, mixed> form initial data
     */
    private function buildFormData(?array $pack, int $idProduct, int $idShop, int $idLang): array
    {
        $languages = \Language::getLanguages(false);
        $data = [
            'id_product' => $idProduct,
            'id_shop' => $idShop,
            'product_name' => [],
            'product_summary' => [],
            'product_description' => [],
            'reference' => '',
            'link_rewrite' => [],
            'categories' => [],
            'default_category' => 0,
            'accessories' => [],
            'width' => 0,
            'height' => 0,
            'depth' => 0,
            'weight' => 0,
            'delivery_time_type' => 'default',
            'delivery_in_stock' => [],
            'delivery_out_stock' => [],
            'price_tax_excl' => 0,
            'price_tax_incl' => 0,
            'tax_rules_group' => (int) \Configuration::get('PS_TAX_RULES_GROUP_DEFAULT'),
            'meta_title' => [],
            'meta_description' => [],
            'pack_type' => 'fixed',
            'pricing_method' => PackConfig::PRICING_FIXED,
            'stock_behavior' => 'components',
            'components_json' => $this->getDefaultComponentsJson(),
        ];

        foreach ($languages as $lang) {
            $idLangValue = (int) $lang['id_lang'];
            $data['product_name'][$idLangValue] = '';
            $data['product_summary'][$idLangValue] = '';
            $data['product_description'][$idLangValue] = '';
            $data['link_rewrite'][$idLangValue] = '';
            $data['delivery_in_stock'][$idLangValue] = '';
            $data['delivery_out_stock'][$idLangValue] = '';
            $data['meta_title'][$idLangValue] = '';
            $data['meta_description'][$idLangValue] = '';
        }

        if ($pack) {
            foreach (['pack_type', 'pricing_method', 'stock_behavior', 'fixed_price_tax_excl', 'forced_price_tax_excl', 'global_discount_percent', 'global_discount_amount_tax_excl'] as $key) {
                $data[$key] = $pack[$key];
            }
            $data['components_json'] = json_encode($this->repository->getComponentsForAdmin((int) $pack['id_pack'], $idLang), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($idProduct > 0) {
            $product = new \Product($idProduct, false, $idLang, $idShop);
            if (\Validate::isLoadedObject($product)) {
                foreach ($languages as $lang) {
                    $idLangValue = (int) $lang['id_lang'];
                    $data['product_name'][$idLangValue] = (string) ($product->name[$idLangValue] ?? '');
                    $data['product_summary'][$idLangValue] = (string) ($product->description_short[$idLangValue] ?? '');
                    $data['product_description'][$idLangValue] = (string) ($product->description[$idLangValue] ?? '');
                    $data['link_rewrite'][$idLangValue] = (string) ($product->link_rewrite[$idLangValue] ?? '');
                    $data['delivery_in_stock'][$idLangValue] = (string) ($product->delivery_in_stock[$idLangValue] ?? '');
                    $data['delivery_out_stock'][$idLangValue] = (string) ($product->delivery_out_stock[$idLangValue] ?? '');
                    $data['meta_title'][$idLangValue] = (string) ($product->meta_title[$idLangValue] ?? '');
                    $data['meta_description'][$idLangValue] = (string) ($product->meta_description[$idLangValue] ?? '');
                }
                $data['reference'] = (string) $product->reference;
                $data['categories'] = array_map('intval', $product->getCategories());
                $data['default_category'] = (int) $product->id_category_default;
                $data['accessories'] = array_map('intval', array_column($product->getAccessories($idLang), 'id_product'));
                $data['width'] = (float) $product->width;
                $data['height'] = (float) $product->height;
                $data['depth'] = (float) $product->depth;
                $data['weight'] = (float) $product->weight;
                $data['price_tax_excl'] = (float) $product->price;
                $data['tax_rules_group'] = (int) $product->id_tax_rules_group;
                $data['price_tax_incl'] = round((float) $product->price * (1 + $this->productService->getTaxRate((int) $product->id_tax_rules_group) / 100), 6);
                $additionalDeliveryTimes = (int) $product->additional_delivery_times;
                $data['delivery_time_type'] = $additionalDeliveryTimes === 0 ? 'none' : ($additionalDeliveryTimes === 1 ? 'default' : 'specific');
            }
        }

        return $data;
    }

    /**
     * Build the effective tax rate of each tax rules group for the JS TTC preview.
     *
     * @return array<int, float> tax rules group identifier to percentage rate
     */
    private function getTaxRateMap(): array
    {
        $rates = [];
        foreach ($this->getTaxRuleGroupsRows() as $row) {
            $rates[(int) $row['id_tax_rules_group']] = $this->productService->getTaxRate((int) $row['id_tax_rules_group']);
        }
        $defaultId = (int) \Configuration::get('PS_TAX_RULES_GROUP_DEFAULT');
        if ($defaultId > 0 && !isset($rates[$defaultId])) {
            $rates[$defaultId] = $this->productService->getTaxRate($defaultId);
        }

        return $rates;
    }

    /**
     * Return the non-deleted tax rules groups regardless of the shop context.
     *
     * The legacy TaxRulesGroup::getTaxRulesGroups() relies on Shop
     * associations that may return an empty set depending on the current shop
     * context, so the list is built from a direct query instead.
     *
     * @return list<array{id_tax_rules_group: int, name: string, active: int}>
     */
    private function getTaxRuleGroupsRows(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT g.id_tax_rules_group, g.name, g.active
             FROM `' . _DB_PREFIX_ . 'tax_rules_group` g
             WHERE g.deleted = 0
             ORDER BY g.active DESC, g.name ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Build the tax rules group choices for the form, always including the
     * configured default group even when it is inactive.
     *
     * @return array<string, int> label to tax rules group identifier map
     */
    private function getTaxRuleChoicesForForm(): array
    {
        $choices = [];
        $used = [];
        foreach ($this->getTaxRuleGroupsRows() as $row) {
            $label = (string) ($row['name'] ?? '');
            if ($label === '') {
                $label = '#' . (int) $row['id_tax_rules_group'];
            }
            if (isset($used[$label])) {
                $label .= ' (#' . (int) $row['id_tax_rules_group'] . ')';
            }
            $used[$label] = true;
            $choices[$label] = (int) $row['id_tax_rules_group'];
        }

        $defaultId = (int) \Configuration::get('PS_TAX_RULES_GROUP_DEFAULT');
        if ($defaultId > 0 && !in_array($defaultId, $choices, true)) {
            $row = \Db::getInstance()->getRow(
                'SELECT g.id_tax_rules_group, g.name
                 FROM `' . _DB_PREFIX_ . 'tax_rules_group` g
                 WHERE g.id_tax_rules_group = ' . $defaultId
            );
            if (is_array($row)) {
                $label = (string) ($row['name'] ?? ('#' . (int) $row['id_tax_rules_group']));
                $choices[$label] = (int) $row['id_tax_rules_group'];
            }
        }

        return $choices;
    }

    /**
     * Build the category choices for the form from the same nested tree as the
     * checkboxes, merging selected identifiers that the tree does not expose.
     *
     * The checkbox tree includes the shop root category while the previous
     * flat list did not, which made the root category value fail as an invalid
     * choice. Selected identifiers (root, inactive categories) are appended so
     * both the default category and the submitted values remain selectable.
     *
     * @param int $idLang language identifier
     * @param array<int, int> $selected selected category identifiers
     *
     * @return array<string, int> label to category identifier map
     */
    private function getCategoryChoicesForForm(int $idLang, array $selected): array
    {
        $flat = $this->getCategoryChoicesFlat($idLang);
        foreach ($selected as $id) {
            $id = (int) $id;
            if ($id > 0 && !isset($flat[$id])) {
                $name = (string) \Category::getName($id, $idLang);
                if ($name === '') {
                    $name = '#' . $id;
                }
                $flat[$id] = $name;
            }
        }

        $choices = [];
        $used = [];
        foreach ($flat as $id => $label) {
            $label = (string) $label;
            if (isset($used[$label])) {
                $label .= ' (#' . (int) $id . ')';
            }
            $used[$label] = true;
            $choices[$label] = (int) $id;
        }

        return $choices;
    }

    /**
     * Return the nested category tree for the current shop.
     *
     * @param int $idLang language identifier
     *
     * @return list<array<string, mixed>> nested category tree rows
     */
    private function getCategoryTree(int $idLang): array
    {
        $rootId = (int) \Configuration::get('PS_HOME_CATEGORY');
        $tree = \Category::getNestedCategories($rootId, $idLang, true);

        return is_array($tree) ? $tree : [];
    }

    /**
     * Flatten the category tree into an id => indented label map.
     *
     * @param int $idLang language identifier
     *
     * @return array<int, string> category identifier to indented label
     */
    private function getCategoryChoicesFlat(int $idLang): array
    {
        $choices = [];
        $walk = function (array $categories, string $prefix = '') use (&$walk, &$choices): void {
            foreach ($categories as $category) {
                $label = $prefix === '' ? (string) $category['name'] : $prefix . ' > ' . $category['name'];
                $choices[(int) $category['id_category']] = $label;
                if (!empty($category['children'])) {
                    $walk($category['children'], $label);
                }
            }
        };
        $walk($this->getCategoryTree($idLang));

        return $choices;
    }

    /**
     * Resolve the display labels of selected associated products.
     *
     * @param array<int, mixed> $accessoryIds selected product identifiers
     * @param int $idLang language identifier
     *
     * @return list<array{id_product: int, name: string}>
     */
    private function getSelectedAccessories(array $accessoryIds, int $idLang): array
    {
        $items = [];
        foreach ($accessoryIds as $id) {
            $idProduct = (int) $id;
            if ($idProduct <= 0) {
                continue;
            }
            $name = (string) \Product::getProductName($idProduct, null, $idLang);
            if ($name === '') {
                continue;
            }
            $items[] = ['id_product' => $idProduct, 'name' => $name];
        }

        return $items;
    }

    /**
     * Validate cross-field pack payload rules before persistence.
     *
     * @param array<string, mixed> $payload form data
     *
     * @return string empty string when valid, otherwise a translated error message
     */
    private function validatePayload(array $payload): string
    {
        $idLangDefault = (int) \Configuration::get('PS_LANG_DEFAULT');
        $names = $payload['product_name'] ?? [];
        $defaultName = is_array($names) ? trim((string) ($names[$idLangDefault] ?? '')) : '';

        if ($defaultName === '') {
            return $this->t('The pack name is required for the default language.', 'Modules.Dydapsconfigurablepacks.Admin');
        }

        if ((string) ($payload['pricing_method'] ?? '') === PackConfig::PRICING_FIXED && (float) ($payload['price_tax_excl'] ?? 0) <= 0) {
            return $this->t('A fixed price greater than zero is required.', 'Modules.Dydapsconfigurablepacks.Admin');
        }

        $categories = array_values(array_unique(array_filter(array_map('intval', (array) ($payload['categories'] ?? [])))));
        $defaultCategory = (int) ($payload['default_category'] ?? 0);
        if ($defaultCategory > 0 && !in_array($defaultCategory, $categories, true)) {
            return $this->t('The default category must be one of the selected categories.', 'Modules.Dydapsconfigurablepacks.Admin');
        }

        return '';
    }

    /**
     * Validate component builder payload before replacing persisted components.
     *
     * @param array<int, mixed> $components submitted component payload
     *
     * @return list<string> translated validation errors
     */
    private function validateComponentsForSave(array $components): array
    {
        $errors = [];
        if (!$components) {
            return [$this->t('Add at least one pack component.', 'Modules.Dydapsconfigurablepacks.Admin')];
        }

        foreach (array_values($components) as $index => $component) {
            if (!is_array($component)) {
                $errors[] = $this->t('A pack component is invalid.', 'Modules.Dydapsconfigurablepacks.Admin');
                continue;
            }
            if ((int) ($component['id_product'] ?? 0) <= 0) {
                $errors[] = $this->t('Each pack component needs a selectable product.', 'Modules.Dydapsconfigurablepacks.Admin');
            }
            if ((int) ($component['quantity'] ?? 0) < 1) {
                $errors[] = $this->t('Each pack component needs a quantity of at least 1.', 'Modules.Dydapsconfigurablepacks.Admin');
            }
            $allowed = array_map('intval', (array) ($component['allowed_combinations'] ?? []));
            foreach ($allowed as $idAttribute) {
                if ($idAttribute <= 0) {
                    $errors[] = $this->t('A pack component declares an invalid allowed combination.', 'Modules.Dydapsconfigurablepacks.Admin');
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Validate a POST action token and convert failures to merchant-facing feedback.
     *
     * @param Request $request current request
     * @param string $tokenId CSRF token identifier
     *
     * @return bool true when the action can continue
     */
    private function validateActionCsrf(Request $request, string $tokenId): bool
    {
        try {
            $this->assertValidCsrf($request, $tokenId);

            return true;
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->t('Security token is invalid. Please try again.', 'Modules.Dydapsconfigurablepacks.Admin'));

            return false;
        }
    }

    /**
     * Return an editable component example for newly created packs.
     *
     * @return string JSON example
     */
    private function getDefaultComponentsJson(): string
    {
        return (string) json_encode([
            [
                'id_product' => 0,
                'name' => 'Component 1',
                'reference' => '',
                'quantity' => 1,
                'optional' => false,
                'component_type' => 'choice',
                'position' => 0,
                'allowed_combinations' => [],
                'allow_customization' => false,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
