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

namespace Dydaps\ConfigurablePacks\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Reads and persists configurable pack definitions.
 *
 * Repository methods are scoped to PrestaShop shop identifiers where a pack can
 * differ per shop. Soft-deleted packs are excluded where the method represents
 * editable/admin state.
 */
final class PackRepository
{
    private \Context $context;

    /**
     * @param \Context $context injected legacy context used to build front-office links
     *
     * @return void
     */
    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    /**
     * Find the pack definition attached to a native product in one shop.
     *
     * @param int $idProduct native PrestaShop product identifier
     * @param int $idShop shop identifier
     *
     * @return array<string, mixed>|null database row, or null when no pack is attached to the product/shop pair
     */
    public function getPackByProduct(int $idProduct, int $idShop): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack` WHERE id_product = ' . (int) $idProduct . ' AND id_shop = ' . (int) $idShop
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Check whether a product is an active configurable pack in the given shop.
     *
     * @param int $idProduct native PrestaShop product identifier
     * @param int $idShop shop identifier
     *
     * @return bool true when the product has an active pack definition for the shop
     */
    public function isPackProduct(int $idProduct, int $idShop): bool
    {
        $pack = $this->getPackByProduct($idProduct, $idShop);

        return $pack !== null && (int) $pack['active'] === 1;
    }

    /**
     * Find a non-deleted pack definition by module identifier.
     *
     * @param int $idPack pack identifier
     *
     * @return array<string, mixed>|null database row, or null when missing or soft-deleted
     */
    public function getPack(int $idPack): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack` WHERE id_pack = ' . (int) $idPack . ' AND deleted_at IS NULL'
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Return all configured components for a pack in display order.
     *
     * Component labels are loaded for the requested language, with a technical
     * fallback name when no translation exists.
     *
     * @param int $idPack pack identifier
     * @param int $idLang language identifier used for component labels
     *
     * @return list<array<string, mixed>>
     */
    public function getComponents(int $idPack, int $idLang): array
    {
        $sql = 'SELECT c.*, COALESCE(cl.name, CONCAT("Component #", c.id_component)) AS name
            FROM `' . _DB_PREFIX_ . 'dydaps_pack_component` c
            LEFT JOIN `' . _DB_PREFIX_ . 'dydaps_pack_component_lang` cl
                ON cl.id_component = c.id_component AND cl.id_lang = ' . (int) $idLang . '
            WHERE c.id_pack = ' . (int) $idPack . '
            ORDER BY c.position ASC, c.id_component ASC';

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Return active products/combinations allowed for a component.
     *
     * @param int $idComponent component identifier
     *
     * @return list<array<string, mixed>>
     */
    public function getAllowedSelections(int $idComponent): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_product`
            WHERE id_component = ' . (int) $idComponent . ' AND active = 1
            ORDER BY position ASC, id_component_product ASC'
        ) ?: [];
    }

    /**
     * Persist the full component composition submitted from the admin form.
     *
     * Existing editable rows are replaced atomically for the pack. Each
     * component keeps a single selectable product with a quantity; every
     * allowed combination is stored as its own component_product row so the
     * front office can offer the merchant-approved combination choices.
     *
     * @param int $idPack pack identifier
     * @param list<array<string, mixed>> $components component definitions
     * @param int $idLang language used for component labels
     *
     * @return void
     */
    public function replaceComponents(int $idPack, array $components, int $idLang): void
    {
        \Db::getInstance()->execute('DELETE cp FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_product` cp INNER JOIN `' . _DB_PREFIX_ . 'dydaps_pack_component` c ON c.id_component = cp.id_component WHERE c.id_pack = ' . (int) $idPack);
        \Db::getInstance()->execute('DELETE cl FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_lang` cl INNER JOIN `' . _DB_PREFIX_ . 'dydaps_pack_component` c ON c.id_component = cl.id_component WHERE c.id_pack = ' . (int) $idPack);
        \Db::getInstance()->delete('dydaps_pack_component', 'id_pack = ' . (int) $idPack);

        foreach (array_values($components) as $position => $component) {
            $quantity = max(1, (int) ($component['quantity'] ?? 1));
            if (!\Db::getInstance()->insert('dydaps_pack_component', [
                'id_pack' => $idPack,
                'position' => (int) ($component['position'] ?? $position),
                'component_type' => pSQL((string) ($component['component_type'] ?? 'choice')),
                'optional' => (int) !empty($component['optional']),
                'quantity' => $quantity,
                'min_quantity' => $quantity,
                'max_quantity' => $quantity,
                'pricing_behavior' => 'native',
                'fixed_price_tax_excl' => 0,
                'discount_percent' => 0,
                'surcharge_tax_excl' => 0,
                'allow_customization' => (int) !empty($component['allow_customization']),
                'customization_required' => (int) !empty($component['customization_required']),
                'active' => 1,
            ])) {
                throw new \RuntimeException('Unable to save pack component.');
            }
            $idComponent = (int) \Db::getInstance()->Insert_ID();
            if (!\Db::getInstance()->insert('dydaps_pack_component_lang', [
                'id_component' => $idComponent,
                'id_lang' => $idLang,
                'name' => pSQL((string) ($component['name'] ?? ('Component #' . ($position + 1)))),
                'description' => pSQL((string) ($component['description'] ?? '')),
            ])) {
                throw new \RuntimeException('Unable to save pack component label.');
            }

            $idProduct = (int) ($component['id_product'] ?? 0);
            if ($idProduct <= 0) {
                continue;
            }
            $allowedCombinations = array_values(array_unique(array_filter(array_map('intval', (array) ($component['allowed_combinations'] ?? [])))));
            $rows = $allowedCombinations ? array_map(static fn (int $idAttribute): array => ['id_product_attribute' => $idAttribute], $allowedCombinations) : [['id_product_attribute' => 0]];
            foreach ($rows as $index => $row) {
                if (!\Db::getInstance()->insert('dydaps_pack_component_product', [
                    'id_component' => $idComponent,
                    'id_product' => $idProduct,
                    'id_product_attribute' => (int) $row['id_product_attribute'],
                    'is_default' => $index === 0 ? 1 : 0,
                    'position' => $index,
                    'active' => 1,
                ])) {
                    throw new \RuntimeException('Unable to save pack component product.');
                }
            }
        }
    }

    /**
     * Return the component payload used by the admin component builder.
     *
     * Each component references a single selectable product and the list of
     * combinations the merchant allowed for the front-office configurator.
     *
     * @param int $idPack pack identifier
     * @param int $idLang language identifier
     *
     * @return list<array<string, mixed>>
     */
    public function getComponentsForAdmin(int $idPack, int $idLang): array
    {
        $components = $this->getComponents($idPack, $idLang);
        $idShop = (int) $this->context->shop->id;
        foreach ($components as &$component) {
            $selections = $this->getAllowedSelections((int) $component['id_component']);
            $idProduct = $selections[0]['id_product'] ?? 0;
            $allowed = [];
            foreach ($selections as $selection) {
                $idAttribute = (int) $selection['id_product_attribute'];
                if ($idAttribute > 0) {
                    $allowed[] = $idAttribute;
                }
            }
            $component['id_product'] = (int) $idProduct;
            $component['reference'] = $idProduct > 0
                ? (string) \Db::getInstance()->getValue(
                    'SELECT reference FROM `' . _DB_PREFIX_ . 'product` WHERE id_product = ' . (int) $idProduct
                )
                : '';
            $component['price_tax_excl'] = $idProduct > 0 ? (float) \Product::getPriceStatic((int) $idProduct, false, null, 6) : 0.0;
            $component['price_tax_incl'] = $idProduct > 0 ? (float) \Product::getPriceStatic((int) $idProduct, true, null, 6) : 0.0;
            $component['allowed_combinations'] = array_values(array_unique($allowed));
            $component['allow_customization'] = (int) ($component['allow_customization'] ?? 0) === 1;
            $component['customization_required'] = (int) ($component['customization_required'] ?? 0) === 1;
            $component['has_customization'] = $idProduct > 0 && $this->productHasCustomization($idProduct);
            $component['combinations'] = $idProduct > 0
                ? array_values(array_map(static fn (array $combination): array => [
                    'id_product_attribute' => (int) $combination['id_product_attribute'],
                    'name' => (string) $combination['attributes_text'],
                ], $this->getBuilderCombinations($idProduct, $idShop, $idLang)))
                : [];
            if (trim((string) $component['name']) === '') {
                $component['name'] = $idProduct > 0
                    ? (string) \Product::getProductName($idProduct, null, $idLang)
                    : ('Component #' . (int) $component['id_component']);
            }
        }
        unset($component);

        return $components;
    }

    /**
     * Return front-office-ready components with product labels and availability.
     *
     * @param int $idPack pack identifier
     * @param int $idLang language identifier
     * @param int $idShop shop identifier
     * @param int $idCurrency currency identifier
     * @param int $idCustomer customer identifier
     *
     * @return list<array<string, mixed>>
     */
    public function describeComponents(int $idPack, int $idLang, int $idShop, int $idCurrency, int $idCustomer): array
    {
        $components = $this->getComponents($idPack, $idLang);
        foreach ($components as &$component) {
            $products = [];
            foreach ($this->getAllowedSelections((int) $component['id_component']) as $selection) {
                $idProduct = (int) $selection['id_product'];
                $idAttribute = (int) $selection['id_product_attribute'];
                $product = new \Product($idProduct, false, $idLang, $idShop);
                if (!\Validate::isLoadedObject($product) || !$this->isProductAvailableInShop($idProduct, $idShop)) {
                    continue;
                }
                if ($idAttribute > 0 && !$this->isCombinationAvailableInShop($idAttribute, $idShop)) {
                    continue;
                }
                $image = \Product::getCover($idProduct);
                $link = $this->context->link;
                $specificPrice = null;
                $priceTaxExcl = (float) \Product::getPriceStatic($idProduct, false, $idAttribute, 6, null, false, true, 1, false, $idCustomer, null, null, $specificPrice, true, true, null, true);
                $priceTaxIncl = (float) \Product::getPriceStatic($idProduct, true, $idAttribute, 6, null, false, true, 1, false, $idCustomer, null, null, $specificPrice, true, true, null, true);
                $products[] = [
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idAttribute,
                    'name' => (string) $product->name,
                    'reference' => $idAttribute > 0 ? $this->getCombinationReference($idAttribute) : (string) $product->reference,
                    'attributes' => $idAttribute > 0 ? \Product::getAttributesParams($idProduct, $idAttribute) : [],
                    'attributes_text' => $idAttribute > 0 ? strip_tags(\Product::getProductName($idProduct, $idAttribute, $idLang)) : '',
                    'image' => $image ? $link->getImageLink($product->link_rewrite, (string) $image['id_image'], \ImageType::getFormattedName('home')) : '',
                    'available_quantity' => (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idAttribute, $idShop),
                    'available' => (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idAttribute, $idShop) > 0,
                    'price_tax_excl' => $priceTaxExcl,
                    'price_tax_incl' => $priceTaxIncl,
                    'impact_tax_excl' => $priceTaxExcl,
                    'impact_tax_incl' => $priceTaxIncl,
                    'has_customization' => $this->productHasCustomization($idProduct),
                    'is_default' => (int) $selection['is_default'],
                ];
            }
            $component['allow_customization'] = (int) ($component['allow_customization'] ?? 0) === 1;
            $component['customization_required'] = (int) ($component['customization_required'] ?? 0) === 1;
            $component['products'] = $products;
        }
        unset($component);

        return $components;
    }

    /**
     * Search active products for the admin component builder.
     *
     * Results are grouped per product: the combination list lets the builder
     * expose the merchant-approved combinations, and the customization flag
     * signals whether the component customization option is relevant.
     *
     * @param string $query search term matched against name, product reference and combination reference
     * @param int $idShop shop identifier used to scope products
     * @param int $idLang language identifier used for labels and attribute names
     *
     * @return list<array{
     *     id_product: int,
     *     name: string,
     *     reference: string,
     *     image: string,
     *     has_combinations: bool,
     *     has_customization: bool,
     *     combinations: list<array{id_product_attribute: int, name: string, reference: string}>
     * }>
     */
    public function searchProductsForBuilder(string $query, int $idShop, int $idLang): array
    {
        $sql = 'SELECT p.id_product, pl.name, p.reference, pl.link_rewrite, ps.active
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps
                ON ps.id_product = p.id_product AND ps.id_shop = ' . (int) $idShop . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.id_product = p.id_product AND pl.id_shop = ' . (int) $idShop . ' AND pl.id_lang = ' . (int) $idLang . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa_ref
                ON pa_ref.id_product = p.id_product
            WHERE ps.active = 1
                AND (
                    pl.name LIKE "%' . pSQL($query) . '%"
                    OR p.reference LIKE "%' . pSQL($query) . '%"
                    OR pa_ref.reference LIKE "%' . pSQL($query) . '%"
                )
            GROUP BY p.id_product, pl.name, p.reference, pl.link_rewrite, ps.active
            ORDER BY pl.name ASC, p.id_product ASC
            LIMIT 20';

        $products = [];
        foreach (\Db::getInstance()->executeS($sql) ?: [] as $row) {
            $idProduct = (int) $row['id_product'];
            $combinations = $this->getBuilderCombinations($idProduct, $idShop, $idLang);
            $cover = \Product::getCover($idProduct);
            $image = '';
            if (is_array($cover) && isset($cover['id_image']) && $this->context->link) {
                $image = (string) $this->context->link->getImageLink((string) $row['link_rewrite'], (string) $cover['id_image'], \ImageType::getFormattedName('small'));
            }
            $products[] = [
                'id_product' => $idProduct,
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
                'image' => $image,
                'has_combinations' => (bool) $combinations,
                'has_customization' => $this->productHasCustomization($idProduct),
                'price_tax_excl' => (float) \Product::getPriceStatic($idProduct, false, null, 6),
                'price_tax_incl' => (float) \Product::getPriceStatic($idProduct, true, null, 6),
                'combinations' => array_map(static fn (array $combination): array => [
                    'id_product_attribute' => (int) $combination['id_product_attribute'],
                    'name' => (string) $combination['attributes_text'],
                    'reference' => (string) ($combination['reference'] ?: $row['reference']),
                ], $combinations),
            ];
        }

        return $products;
    }

    /**
     * Return whether a product exposes native customization fields.
     *
     * Customization fields are soft-deleted through the is_deleted flag on both
     * PrestaShop 8 and 9.
     *
     * @param int $idProduct product identifier
     *
     * @return bool true when the product has at least one active customization field
     */
    private function productHasCustomization(int $idProduct): bool
    {
        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customization_field`
            WHERE id_product = ' . (int) $idProduct . ' AND is_deleted = 0'
        );

        return $count > 0;
    }

    /**
     * Return active native customization fields of one product.
     *
     * Field names are loaded in the requested language and shop; empty names
     * fall back to a technical placeholder so the configurator never renders a
     * blank label.
     *
     * @param int $idProduct product identifier
     * @param int $idLang language identifier used for field labels
     * @param int $idShop shop identifier used for field labels
     *
     * @return list<array{
     *     id_customization_field: int,
     *     type: int,
     *     required: int,
     *     name: string
     * }>
     */
    public function getCustomizationFieldsForProduct(int $idProduct, int $idLang, int $idShop): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT cf.id_customization_field, cf.type, cf.required, cfl.name
            FROM `' . _DB_PREFIX_ . 'customization_field` cf
            LEFT JOIN `' . _DB_PREFIX_ . 'customization_field_lang` cfl
                ON cfl.id_customization_field = cf.id_customization_field
                AND cfl.id_lang = ' . (int) $idLang . ' AND cfl.id_shop = ' . (int) $idShop . '
            WHERE cf.id_product = ' . (int) $idProduct . ' AND cf.is_deleted = 0
            ORDER BY cf.id_customization_field ASC'
        ) ?: [];

        $fields = [];
        foreach ($rows as $row) {
            $fields[] = [
                'id_customization_field' => (int) $row['id_customization_field'],
                'type' => (int) ($row['type'] ?? 1),
                'required' => (int) ($row['required'] ?? 0),
                'name' => trim((string) ($row['name'] ?? '')) !== ''
                    ? (string) $row['name']
                    : sprintf('Customization #%d', (int) $row['id_customization_field']),
            ];
        }

        return $fields;
    }

    /**
     * Insert or update a pack definition.
     *
     * @param array{
     *     id_pack?: int,
     *     id_product: int,
     *     id_shop: int,
     *     active?: int|bool,
     *     pack_type?: string,
     *     pricing_method?: string,
     *     fixed_price_tax_excl?: float|int|string,
     *     price_tax_excl?: float|int|string,
     *     forced_price_tax_excl?: float|int|string,
     *     global_discount_percent?: float|int|string,
     *     global_discount_amount_tax_excl?: float|int|string,
     *     stock_behavior?: string
     * } $data Form payload
     *
     * @return int pack identifier
     */
    public function savePack(array $data): int
    {
        $idPack = (int) ($data['id_pack'] ?? 0);
        $payload = [
            'id_product' => (int) $data['id_product'],
            'id_shop' => (int) $data['id_shop'],
            'active' => (int) ($data['active'] ?? 0),
            'pack_type' => pSQL((string) ($data['pack_type'] ?? 'fixed')),
            'pricing_method' => pSQL((string) ($data['pricing_method'] ?? 'fixed')),
            'fixed_price_tax_excl' => (float) ($data['fixed_price_tax_excl'] ?? $data['price_tax_excl'] ?? 0),
            'forced_price_tax_excl' => (float) ($data['forced_price_tax_excl'] ?? 0),
            'global_discount_percent' => (float) ($data['global_discount_percent'] ?? 0),
            'global_discount_amount_tax_excl' => (float) ($data['global_discount_amount_tax_excl'] ?? 0),
            'stock_behavior' => pSQL((string) ($data['stock_behavior'] ?? 'components')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($idPack > 0) {
            \Db::getInstance()->update('dydaps_pack', $payload, 'id_pack = ' . $idPack);
            $this->applyContainerStockPolicy((int) $data['id_product'], (int) $data['id_shop'], (string) $payload['stock_behavior']);

            return $idPack;
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        \Db::getInstance()->insert('dydaps_pack', $payload);
        $idPack = (int) \Db::getInstance()->Insert_ID();
        $this->applyContainerStockPolicy((int) $data['id_product'], (int) $data['id_shop'], (string) $payload['stock_behavior']);

        return $idPack;
    }

    /**
     * Apply the native stock policy required by the module container product.
     *
     * In component stock mode, PrestaShop must not reject checkout because the
     * container has no own stock; component validation remains the business
     * stock gate.
     *
     * @param int $idProduct native pack container product identifier
     * @param int $idShop shop identifier
     * @param string $stockBehavior pack stock behavior
     *
     * @return void
     */
    private function applyContainerStockPolicy(int $idProduct, int $idShop, string $stockBehavior): void
    {
        if ($idProduct <= 0 || $idShop <= 0 || $stockBehavior !== 'components') {
            return;
        }

        if (class_exists('StockAvailable') && method_exists('StockAvailable', 'setProductOutOfStock')) {
            \StockAvailable::setProductOutOfStock($idProduct, 1, $idShop);
        }
    }

    /**
     * Return active combination labels for the builder search result.
     *
     * @param int $idProduct product identifier
     * @param int $idLang language identifier
     *
     * @return list<array{id_product_attribute: int, reference: string, attributes_text: string}>
     */
    private function getBuilderCombinations(int $idProduct, int $idShop, int $idLang): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT pa.id_product_attribute, pa.reference,
                GROUP_CONCAT(CONCAT(agl.name, ": ", al.name) ORDER BY ag.position, a.position SEPARATOR ", ") AS attributes_text
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` pas
                ON pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = ' . (int) $idShop . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                ON pac.id_product_attribute = pa.id_product_attribute
            INNER JOIN `' . _DB_PREFIX_ . 'attribute` a
                ON a.id_attribute = pac.id_attribute
            INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al
                ON al.id_attribute = a.id_attribute AND al.id_lang = ' . (int) $idLang . '
            INNER JOIN `' . _DB_PREFIX_ . 'attribute_group` ag
                ON ag.id_attribute_group = a.id_attribute_group
            INNER JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl
                ON agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = ' . (int) $idLang . '
            WHERE pa.id_product = ' . (int) $idProduct . '
            GROUP BY pa.id_product_attribute, pa.reference
            ORDER BY pa.id_product_attribute ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return whether a product is active in the requested shop.
     *
     * @param int $idProduct product identifier
     * @param int $idShop shop identifier
     *
     * @return bool true when the product has an active product_shop row
     */
    public function isProductAvailableInShop(int $idProduct, int $idShop): bool
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop`
            WHERE id_product = ' . (int) $idProduct . '
            AND id_shop = ' . (int) $idShop . '
            AND active = 1'
        ) > 0;
    }

    /**
     * Return whether a product combination is associated with the requested shop.
     *
     * @param int $idProductAttribute combination identifier
     * @param int $idShop shop identifier
     *
     * @return bool true when the combination is scoped to the shop
     */
    public function isCombinationAvailableInShop(int $idProductAttribute, int $idShop): bool
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_attribute_shop`
            WHERE id_product_attribute = ' . (int) $idProductAttribute . '
            AND id_shop = ' . (int) $idShop
        ) > 0;
    }

    /**
     * Return the stored reference for a product combination.
     *
     * PrestaShop 9 no longer exposes the legacy Combination::getReference()
     * helper used by earlier versions, while the database column remains stable.
     *
     * @param int $idProductAttribute combination identifier
     *
     * @return string combination reference, or an empty string when unavailable
     */
    public function getCombinationReference(int $idProductAttribute): string
    {
        if ($idProductAttribute <= 0) {
            return '';
        }

        return (string) \Db::getInstance()->getValue(
            'SELECT reference FROM `' . _DB_PREFIX_ . 'product_attribute`
            WHERE id_product_attribute = ' . (int) $idProductAttribute
        );
    }

    /**
     * Soft-delete a pack and disable it for the front office.
     *
     * @param int $idPack pack identifier
     *
     * @return bool true when the database update succeeds
     */
    public function deletePack(int $idPack): bool
    {
        return \Db::getInstance()->update('dydaps_pack', ['deleted_at' => date('Y-m-d H:i:s'), 'active' => 0], 'id_pack = ' . (int) $idPack);
    }
}
