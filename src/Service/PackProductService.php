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

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Creates and updates the native PrestaShop product backing a configurable pack.
 *
 * The product carries the pack catalog data (name, descriptions, categories,
 * accessories, dimensions, delivery time, sale price, tax rules and SEO) while
 * the module pack tables store the configurator composition.
 */
final class PackProductService
{
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
     * Set the pack product cover image from an uploaded file.
     *
     * The file is validated, added as a legacy image with the first available
     * position, resized into the product image folder and its generated types
     * are regenerated so the front-office cover renders in every size.
     *
     * @param int $idProduct pack product identifier
     * @param UploadedFile $file uploaded image file
     * @param int $idShop pack shop identifier
     *
     * @return void
     */
    public function setCoverImage(int $idProduct, UploadedFile $file, int $idShop): void
    {
        $fileData = [
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'tmp_name' => $file->getPathname(),
            'error' => $file->getError(),
            'size' => $file->getSize(),
        ];
        $uploadError = \ImageManager::validateUpload($fileData);
        if ($uploadError !== false) {
            throw new \RuntimeException(is_string($uploadError) ? $uploadError : 'The uploaded cover image is invalid.');
        }
        if (!\ImageManager::isCorrectImageFileExt($fileData['name'])) {
            throw new \RuntimeException('The uploaded cover image format is not supported.');
        }

        if (!is_dir(_PS_PRODUCT_IMG_DIR_)) {
            @mkdir(_PS_PRODUCT_IMG_DIR_, 0777, true);
        }

        $image = new \Image();
        $image->id_product = (int) $idProduct;
        $image->position = (int) \Image::getHighestPosition($idProduct) + 1;
        $image->cover = true;
        if (!$image->add()) {
            throw new \RuntimeException('Unable to create the pack product image.');
        }

        $imagePath = _PS_PRODUCT_IMG_DIR_ . $image->getImgFolder() . (int) $image->id . '.jpg';
        if (!\ImageManager::resize($fileData['tmp_name'], $imagePath)) {
            $image->delete();
            throw new \RuntimeException('Unable to resize the pack cover image.');
        }

        foreach (\ImageType::getImagesTypes('products') as $imageType) {
            \ImageManager::resize(
                $imagePath,
                _PS_PRODUCT_IMG_DIR_ . $image->getImgFolder() . (int) $image->id . '-' . (string) $imageType['name'] . '.jpg',
                (int) $imageType['width'],
                (int) $imageType['height']
            );
        }

        $db = \Db::getInstance();
        $db->update('image', ['cover' => 0], 'id_product = ' . (int) $idProduct);
        $db->update('image', ['cover' => 1], 'id_image = ' . (int) $image->id);
        $db->update('image_shop', ['cover' => 0], 'id_product = ' . (int) $idProduct . ' AND id_shop = ' . (int) $idShop);
        $db->update('image_shop', ['cover' => 1], 'id_image = ' . (int) $image->id . ' AND id_shop = ' . (int) $idShop);
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
            $address->id_country = (int) \Configuration::get('PS_COUNTRY_DEFAULT');
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
     * Content fields are stored per language with a fallback to the default
     * language value, and the delivery time type is mapped to the native
     * additional_delivery_times product setting.
     *
     * @param \Product $product product model to mutate
     * @param array<string, mixed> $data form payload
     * @param int $idShop pack shop identifier
     *
     * @return void
     */
    private function applyProductData(\Product $product, array $data, int $idShop): void
    {
        $idLangDefault = (int) \Configuration::get('PS_LANG_DEFAULT');
        $names = is_array($data['product_name'] ?? null) ? $data['product_name'] : [];
        $name = trim((string) ($names[$idLangDefault] ?? ''));
        if ($name === '') {
            foreach ($names as $value) {
                $name = trim((string) $value);
                if ($name !== '') {
                    break;
                }
            }
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
        $product->active = true;
        $product->available_for_order = true;
        $product->show_price = true;
        $product->visibility = 'both';
        $product->is_virtual = false;
        $deliveryTimeType = (string) ($data['delivery_time_type'] ?? 'default');
        $product->additional_delivery_times = $deliveryTimeType === 'none' ? 0 : ($deliveryTimeType === 'specific' ? 2 : 1);
        if (property_exists($product, 'product_type')) {
            $product->product_type = 'standard';
        }

        foreach (\Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $langName = trim((string) ($names[$idLang] ?? $name));
            if ($langName === '') {
                $langName = $name;
            }
            $linkRewrite = $this->langValue($data, 'link_rewrite', $idLang);
            if ($linkRewrite === '') {
                $linkRewrite = $this->slugify($langName !== '' ? $langName : 'pack-' . time());
            }
            $product->name[$idLang] = $langName;
            $product->description[$idLang] = $this->langValue($data, 'product_description', $idLang);
            $product->description_short[$idLang] = $this->langValue($data, 'product_summary', $idLang);
            $product->meta_title[$idLang] = $this->langValue($data, 'meta_title', $idLang);
            $product->meta_description[$idLang] = $this->langValue($data, 'meta_description', $idLang);
            $product->link_rewrite[$idLang] = $linkRewrite;
            $product->delivery_in_stock[$idLang] = $this->langValue($data, 'delivery_in_stock', $idLang);
            $product->delivery_out_stock[$idLang] = $this->langValue($data, 'delivery_out_stock', $idLang);
        }
    }

    /**
     * Read one language value from a multilingual form field.
     *
     * @param array<string, mixed> $data form payload
     * @param string $key multilingual field key
     * @param int $idLang language identifier
     *
     * @return string language value, or an empty string when missing
     */
    private function langValue(array $data, string $key, int $idLang): string
    {
        $values = $data[$key] ?? null;
        if (!is_array($values)) {
            return '';
        }
        $value = $values[$idLang] ?? '';
        $value = is_string($value) ? $value : (string) $value;

        return $value;
    }

    /**
     * Build a URL-safe slug from a product label without relying on legacy Tools.
     *
     * @param string $value source label
     *
     * @return string slug, or a time-based fallback when the result is empty
     */
    private function slugify(string $value): string
    {
        $ascii = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'æ' => 'ae',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'À' => 'a', 'Â' => 'a', 'Ä' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Î' => 'i', 'Ï' => 'i',
            'Ô' => 'o', 'Ö' => 'o',
            'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c',
        ]);
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-'));

        return $slug !== '' ? $slug : 'pack-' . time();
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
