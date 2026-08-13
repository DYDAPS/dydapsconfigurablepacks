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

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Creates and updates the native PrestaShop product backing a configurable pack.
 *
 * The product carries the pack catalog data (name, descriptions, categories,
 * accessories, dimensions, delivery time, sale price, tax rules and SEO) while
 * the module pack tables store the configurator composition.
 */
final class PackProductService
{
    private \Context $context;

    /**
     * @param \Context $context injected legacy context used to scope shop operations
     *
     * @return void
     */
    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    /**
     * Create the pack product or update an existing one.
     *
     * @param int|null $idProduct existing product identifier, or null for creation
     * @param array<string, mixed> $data form payload containing the product fields
     * @param int $idShop pack shop identifier
     *
     * @return int product identifier
     */
    public function createOrUpdate(?int $idProduct, array $data, int $idShop): int
    {
        $product = null;
        if ($idProduct !== null && $idProduct > 0) {
            $product = new \Product((int) $idProduct);
            if (!\Validate::isLoadedObject($product)) {
                throw new \RuntimeException('The linked pack product no longer exists.');
            }
        } else {
            $product = new \Product();
        }

        $this->applyProductData($product, $data, $idShop);

        if ($product->id) {
            if (!$product->update()) {
                throw new \RuntimeException('Unable to update the pack product.');
            }
        } else {
            if (!$product->add()) {
                throw new \RuntimeException('Unable to create the pack product.');
            }
        }

        $this->ensureShopAssociation($product, $idShop);
        $this->persistRelations($product, $data, $idShop);

        return (int) $product->id;
    }

    /**
     * Compute the effective tax rate of a tax rules group for the shop country.
     *
     * @param int $idTaxRulesGroup tax rules group identifier
     *
     * @return float percentage tax rate
     */
    public function getTaxRate(int $idTaxRulesGroup): float
    {
        if ($idTaxRulesGroup <= 0) {
            return 0.0;
        }

        try {
            $address = new \Address();
            $address->id_country = (int) \Country::getDefaultCountryId();
            $address->id_state = 0;
            $address->postcode = '00000';
            $calculator = \TaxManagerFactory::getManager($address, (int) $idTaxRulesGroup)->getTaxCalculator();

            return (float) $calculator->getTotalRate();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Fill the Product model with the submitted catalog and pricing data.
     *
     * @param \Product $product product model to mutate
     * @param array<string, mixed> $data form payload
     * @param int $idShop pack shop identifier
     *
     * @return void
     */
    private function applyProductData(\Product $product, array $data, int $idShop): void
    {
        $name = trim((string) ($data['product_name'] ?? ''));
        $linkRewrite = trim((string) ($data['link_rewrite'] ?? ''));
        if ($linkRewrite === '') {
            $linkRewrite = \Tools::link_rewrite($name !== '' ? $name : 'pack-' . time());
        }

        foreach (\Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $product->name[$idLang] = $name;
            $product->description[$idLang] = (string) ($data['product_description'] ?? '');
            $product->description_short[$idLang] = (string) ($data['product_summary'] ?? '');
            $product->meta_title[$idLang] = (string) ($data['meta_title'] ?? '');
            $product->meta_description[$idLang] = (string) ($data['meta_description'] ?? '');
            $product->link_rewrite[$idLang] = $linkRewrite;
            $product->delivery_in_stock[$idLang] = (string) ($data['delivery_time'] ?? '');
        }

        $reference = trim((string) ($data['reference'] ?? ''));
        $product->reference = $reference !== '' ? $reference : ('PACK-' . strtoupper(substr((string) hash('sha256', (string) uniqid('', true)), 0, 8)));
        $product->price = (float) ($data['price_tax_excl'] ?? 0);
        $product->id_tax_rules_group = (int) ($data['tax_rules_group'] ?? 0);
        $product->width = (float) ($data['width'] ?? 0);
        $product->height = (float) ($data['height'] ?? 0);
        $product->depth = (float) ($data['depth'] ?? 0);
        $product->weight = (float) ($data['weight'] ?? 0);
        $product->id_category_default = (int) ($data['default_category'] ?? 0);
        $product->id_shop_default = (int) $idShop;
        $product->active = 1;
        $product->available_for_order = 1;
        $product->show_price = 1;
        $product->visibility = 'both';
        $product->is_virtual = 0;
        $product->additional_delivery_times = 2;
        if (property_exists($product, 'product_type')) {
            $product->product_type = 'standard';
        }
    }

    /**
     * Store category, default category and accessory relations for the product.
     *
     * @param \Product $product persisted product model
     * @param array<string, mixed> $data form payload
     * @param int $idShop pack shop identifier
     *
     * @return void
     */
    private function persistRelations(\Product $product, array $data, int $idShop): void
    {
        $categories = array_values(array_unique(array_filter(array_map('intval', (array) ($data['categories'] ?? [])))));
        $defaultCategory = (int) ($data['default_category'] ?? 0);
        if ($defaultCategory > 0 && !in_array($defaultCategory, $categories, true)) {
            $categories[] = $defaultCategory;
        }

        if ($categories) {
            $product->updateCategories($categories);
        }
        if ($defaultCategory > 0) {
            if (method_exists($product, 'updateDefaultCategory')) {
                $product->updateDefaultCategory($defaultCategory);
            } else {
                \Db::getInstance()->update('product_shop', ['id_category_default' => $defaultCategory], 'id_product = ' . (int) $product->id . ' AND id_shop = ' . (int) $idShop);
            }
        }

        $accessories = array_values(array_unique(array_filter(array_map('intval', (array) ($data['accessories'] ?? [])))));
        if (method_exists($product, 'changeAccessories')) {
            $product->changeAccessories($accessories);
        } else {
            \Db::getInstance()->delete('accessory', 'id_product_1 = ' . (int) $product->id);
            foreach ($accessories as $idProduct2) {
                \Db::getInstance()->insert('accessory', ['id_product_1' => (int) $product->id, 'id_product_2' => (int) $idProduct2]);
            }
        }
    }

    /**
     * Guarantee product_shop and product_lang rows exist for the pack shop.
     *
     * The admin context shop normally matches the pack shop; this defensive step
     * copies rows from another associated shop when the pack targets another shop.
     *
     * @param \Product $product persisted product model
     * @param int $idShop pack shop identifier
     *
     * @return void
     */
    private function ensureShopAssociation(\Product $product, int $idShop): void
    {
        $db = \Db::getInstance();
        $idProduct = (int) $product->id;

        $hasShop = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE id_product = ' . $idProduct . ' AND id_shop = ' . (int) $idShop
        ) > 0;
        if (!$hasShop) {
            $row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'product_shop` WHERE id_product = ' . $idProduct . ' ORDER BY id_shop ASC');
            if (is_array($row)) {
                unset($row['id_shop']);
                $row['id_shop'] = (int) $idShop;
                $db->insert('product_shop', $row);
            }
        }

        foreach (\Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $hasLang = (int) $db->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_lang` WHERE id_product = ' . $idProduct . ' AND id_shop = ' . (int) $idShop . ' AND id_lang = ' . $idLang
            ) > 0;
            if (!$hasLang) {
                $row = $db->getRow(
                    'SELECT * FROM `' . _DB_PREFIX_ . 'product_lang` WHERE id_product = ' . $idProduct . ' AND id_lang = ' . $idLang . ' ORDER BY id_shop ASC'
                );
                if (is_array($row)) {
                    unset($row['id_shop']);
                    $row['id_shop'] = (int) $idShop;
                    $db->insert('product_lang', $row);
                }
            }
        }
    }
}
