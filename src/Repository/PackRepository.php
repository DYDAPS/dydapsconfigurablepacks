<?php
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
    /**
     * Find the pack definition attached to a native product in one shop.
     *
     * @param int $idProduct Native PrestaShop product identifier.
     * @param int $idShop Shop identifier.
     *
     * @return array<string, mixed>|null Database row, or null when no pack is attached to the product/shop pair.
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
     * @param int $idProduct Native PrestaShop product identifier.
     * @param int $idShop Shop identifier.
     *
     * @return bool True when the product has an active pack definition for the shop.
     */
    public function isPackProduct(int $idProduct, int $idShop): bool
    {
        $pack = $this->getPackByProduct($idProduct, $idShop);

        return $pack !== null && (int) $pack['active'] === 1;
    }

    /**
     * Find a non-deleted pack definition by module identifier.
     *
     * @param int $idPack Pack identifier.
     *
     * @return array<string, mixed>|null Database row, or null when missing or soft-deleted.
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
     * @param int $idPack Pack identifier.
     * @param int $idLang Language identifier used for component labels.
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
     * @param int $idComponent Component identifier.
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
     * Existing editable rows are replaced atomically for the pack. The payload
     * shape intentionally mirrors the component tables so every supported
     * pricing, stock and quantity field is administrable.
     *
     * @param int $idPack Pack identifier.
     * @param list<array<string, mixed>> $components Component definitions.
     * @param int $idLang Language used for component labels.
     *
     * @return void
     */
    public function replaceComponents(int $idPack, array $components, int $idLang): void
    {
        \Db::getInstance()->execute('DELETE cp FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_product` cp INNER JOIN `' . _DB_PREFIX_ . 'dydaps_pack_component` c ON c.id_component = cp.id_component WHERE c.id_pack = ' . (int) $idPack);
        \Db::getInstance()->execute('DELETE cl FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_lang` cl INNER JOIN `' . _DB_PREFIX_ . 'dydaps_pack_component` c ON c.id_component = cl.id_component WHERE c.id_pack = ' . (int) $idPack);
        \Db::getInstance()->delete('dydaps_pack_component', 'id_pack = ' . (int) $idPack);

        foreach (array_values($components) as $position => $component) {
            if (!\Db::getInstance()->insert('dydaps_pack_component', [
                'id_pack' => $idPack,
                'position' => (int) ($component['position'] ?? $position),
                'component_type' => pSQL((string) ($component['component_type'] ?? 'choice')),
                'optional' => (int) !empty($component['optional']),
                'quantity' => max(1, (int) ($component['quantity'] ?? 1)),
                'min_quantity' => max(0, (int) ($component['min_quantity'] ?? 1)),
                'max_quantity' => max(1, (int) ($component['max_quantity'] ?? ($component['quantity'] ?? 1))),
                'pricing_behavior' => pSQL((string) ($component['pricing_behavior'] ?? 'native')),
                'fixed_price_tax_excl' => (float) ($component['fixed_price_tax_excl'] ?? 0),
                'discount_percent' => (float) ($component['discount_percent'] ?? 0),
                'surcharge_tax_excl' => (float) ($component['surcharge_tax_excl'] ?? 0),
                'active' => (int) ($component['active'] ?? 1),
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

            foreach (array_values((array) ($component['products'] ?? [])) as $productPosition => $product) {
                if (!is_array($product) || (int) ($product['id_product'] ?? 0) <= 0) {
                    continue;
                }
                if (!\Db::getInstance()->insert('dydaps_pack_component_product', [
                    'id_component' => $idComponent,
                    'id_product' => (int) $product['id_product'],
                    'id_product_attribute' => (int) ($product['id_product_attribute'] ?? 0),
                    'is_default' => (int) !empty($product['is_default']),
                    'position' => (int) ($product['position'] ?? $productPosition),
                    'active' => (int) ($product['active'] ?? 1),
                ])) {
                    throw new \RuntimeException('Unable to save pack component product.');
                }
            }
        }
    }

    /**
     * Return the full component payload used by the admin JSON editor.
     *
     * @param int $idPack Pack identifier.
     * @param int $idLang Language identifier.
     *
     * @return list<array<string, mixed>>
     */
    public function getComponentsForAdmin(int $idPack, int $idLang): array
    {
        $components = $this->getComponents($idPack, $idLang);
        foreach ($components as &$component) {
            $products = [];
            foreach ($this->getAllowedSelections((int) $component['id_component']) as $selection) {
                $products[] = array_merge(
                    $selection,
                    $this->buildProductSearchRow(
                        (int) $selection['id_product'],
                        (int) $selection['id_product_attribute'],
                        (string) \Product::getProductName((int) $selection['id_product'], null, $idLang),
                        '',
                        '',
                        $idLang
                    )
                );
            }
            $component['products'] = $products;
        }
        unset($component);

        return $components;
    }

    /**
     * Return front-office-ready components with product labels and availability.
     *
     * @param int $idPack Pack identifier.
     * @param int $idLang Language identifier.
     * @param int $idShop Shop identifier.
     * @param int $idCurrency Currency identifier.
     * @param int $idCustomer Customer identifier.
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
                if (!\Validate::isLoadedObject($product) || !(bool) $product->active) {
                    continue;
                }
                $image = \Product::getCover($idProduct);
                $link = \Context::getContext()->link;
                $specificPrice = null;
                $priceTaxExcl = (float) \Product::getPriceStatic($idProduct, false, $idAttribute, 6, null, false, true, 1, false, $idCustomer, null, null, $specificPrice, true, true, null, true);
                $priceTaxIncl = (float) \Product::getPriceStatic($idProduct, true, $idAttribute, 6, null, false, true, 1, false, $idCustomer, null, null, $specificPrice, true, true, null, true);
                $products[] = [
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idAttribute,
                    'name' => (string) $product->name,
                    'reference' => $idAttribute > 0 ? (string) \Combination::getReference($idAttribute) : (string) $product->reference,
                    'attributes' => $idAttribute > 0 ? \Product::getAttributesParams($idProduct, $idAttribute) : [],
                    'attributes_text' => $idAttribute > 0 ? strip_tags(\Product::getProductName($idProduct, $idAttribute, $idLang)) : '',
                    'image' => $image ? $link->getImageLink($product->link_rewrite, (string) $image['id_image'], 'home_default') : '',
                    'available_quantity' => (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idAttribute, $idShop),
                    'available' => (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idAttribute, $idShop) > 0,
                    'price_tax_excl' => $priceTaxExcl,
                    'price_tax_incl' => $priceTaxIncl,
                    'impact_tax_excl' => $priceTaxExcl,
                    'impact_tax_incl' => $priceTaxIncl,
                    'is_default' => (int) $selection['is_default'],
                ];
            }
            $component['products'] = $products;
        }
        unset($component);

        return $components;
    }

    /**
     * Search active products and combinations for the admin component builder.
     *
     * @param string $query Search term matched against name, product reference and combination reference.
     * @param int $idShop Shop identifier used to scope products.
     * @param int $idLang Language identifier used for labels and attribute names.
     *
     * @return list<array{
     *     id_product: int,
     *     id_product_attribute: int,
     *     name: string,
     *     reference: string,
     *     attributes_text: string,
     *     image: string
     * }>
     */
    public function searchProductsForBuilder(string $query, int $idShop, int $idLang): array
    {
        $sql = 'SELECT p.id_product, pl.name, p.reference, ps.active
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
            GROUP BY p.id_product, pl.name, p.reference, ps.active
            ORDER BY pl.name ASC, p.id_product ASC
            LIMIT 20';

        $products = [];
        foreach (\Db::getInstance()->executeS($sql) ?: [] as $row) {
            $idProduct = (int) $row['id_product'];
            $combinations = $this->getBuilderCombinations($idProduct, $idLang);
            if (!$combinations) {
                $products[] = $this->buildProductSearchRow($idProduct, 0, (string) $row['name'], (string) $row['reference'], '', $idLang);
                continue;
            }

            foreach ($combinations as $combination) {
                $products[] = $this->buildProductSearchRow(
                    $idProduct,
                    (int) $combination['id_product_attribute'],
                    (string) $row['name'],
                    (string) ($combination['reference'] ?: $row['reference']),
                    (string) $combination['attributes_text'],
                    $idLang
                );
            }
        }

        return $products;
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
     *     forced_price_tax_excl?: float|int|string,
     *     global_discount_percent?: float|int|string,
     *     global_discount_amount_tax_excl?: float|int|string,
     *     stock_behavior?: string
     * } $data Form payload.
     *
     * @return int Pack identifier.
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
            'fixed_price_tax_excl' => (float) ($data['fixed_price_tax_excl'] ?? 0),
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
     * @param int $idProduct Native pack container product identifier.
     * @param int $idShop Shop identifier.
     * @param string $stockBehavior Pack stock behavior.
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
     * @param int $idProduct Product identifier.
     * @param int $idLang Language identifier.
     *
     * @return list<array{id_product_attribute: int, reference: string, attributes_text: string}>
     */
    private function getBuilderCombinations(int $idProduct, int $idLang): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT pa.id_product_attribute, pa.reference,
                GROUP_CONCAT(CONCAT(agl.name, ": ", al.name) ORDER BY ag.position, a.position SEPARATOR ", ") AS attributes_text
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
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
     * Build one product choice row for the builder.
     *
     * @param int $idProduct Product identifier.
     * @param int $idProductAttribute Combination identifier.
     * @param string $name Product name.
     * @param string $reference Product or combination reference.
     * @param string $attributesText Human-readable combination attributes.
     * @param int $idLang Language identifier.
     *
     * @return array{
     *     id_product: int,
     *     id_product_attribute: int,
     *     name: string,
     *     reference: string,
     *     attributes_text: string,
     *     image: string
     * }
     */
    private function buildProductSearchRow(int $idProduct, int $idProductAttribute, string $name, string $reference, string $attributesText, int $idLang): array
    {
        $product = new \Product($idProduct, false, $idLang);
        if ($reference === '') {
            $reference = $idProductAttribute > 0 ? (string) \Combination::getReference($idProductAttribute) : (string) $product->reference;
        }
        if ($attributesText === '' && $idProductAttribute > 0) {
            $attributesText = strip_tags((string) \Product::getProductName($idProduct, $idProductAttribute, $idLang));
        }
        $cover = \Product::getCover($idProduct);
        $image = '';
        if (is_array($cover) && isset($cover['id_image']) && \Context::getContext()->link) {
            $image = (string) \Context::getContext()->link->getImageLink((string) $product->link_rewrite, (string) $cover['id_image'], 'small_default');
        }

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'name' => $name,
            'reference' => $reference,
            'attributes_text' => $attributesText,
            'image' => $image,
        ];
    }

    /**
     * Soft-delete a pack and disable it for the front office.
     *
     * @param int $idPack Pack identifier.
     *
     * @return bool True when the database update succeeds.
     */
    public function deletePack(int $idPack): bool
    {
        return \Db::getInstance()->update('dydaps_pack', ['deleted_at' => date('Y-m-d H:i:s'), 'active' => 0], 'id_pack = ' . (int) $idPack);
    }
}
