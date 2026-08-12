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
 * Persists configured pack selections attached to native PrestaShop carts.
 */
final class PackCartRepository
{
    /**
     * Insert or update one cart configuration identified by its stable hash.
     *
     * The unique cart/product/hash key lets a cart contain multiple different
     * configurations of the same pack product without overwriting each other.
     *
     * @param int $idCart cart identifier
     * @param int $idProduct native PrestaShop product sold as the pack
     * @param int $idProductAttribute pack product combination identifier, usually zero
     * @param int $idCustomization native PrestaShop customization identifier used to split cart rows
     * @param string $hash stable configuration hash
     * @param int $quantity absolute pack quantity to store for the native cart line
     * @param array<string, mixed> $configuration serializable pack configuration snapshot
     * @param array{
     *     unit_tax_excl?: float,
     *     unit_tax_incl?: float
     * } $price Unit price snapshot in the cart currency
     *
     * @return bool true when the row is inserted or updated
     */
    public function saveConfiguration(int $idCart, int $idProduct, int $idProductAttribute, int $idCustomization, string $hash, int $quantity, array $configuration, array $price): bool
    {
        $existingId = (int) \Db::getInstance()->getValue(
            'SELECT id_cart_pack FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart`
            WHERE id_cart = ' . (int) $idCart . ' AND id_product = ' . (int) $idProduct . '
            AND id_product_attribute = ' . (int) $idProductAttribute . ' AND configuration_hash = "' . pSQL($hash) . '"'
        );

        $normalizedQuantity = max(1, (int) $quantity);
        $configurationJson = (string) json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payload = [
            'id_cart' => $idCart,
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'id_customization' => $idCustomization,
            'configuration_hash' => pSQL($hash),
            'configuration_json' => pSQL($configurationJson),
            'quantity' => $normalizedQuantity,
            'unit_price_tax_excl' => (float) ($price['unit_tax_excl'] ?? 0),
            'unit_price_tax_incl' => (float) ($price['unit_tax_incl'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existingId > 0) {
            unset($payload['id_customization']);

            return \Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'dydaps_pack_cart`
                SET configuration_json = "' . pSQL($configurationJson) . '",
                    quantity = ' . $normalizedQuantity . ',
                    unit_price_tax_excl = ' . (float) $payload['unit_price_tax_excl'] . ',
                    unit_price_tax_incl = ' . (float) $payload['unit_price_tax_incl'] . ',
                    updated_at = "' . pSQL((string) $payload['updated_at']) . '"
                WHERE id_cart_pack = ' . $existingId
            );
        }

        $payload['created_at'] = date('Y-m-d H:i:s');

        return \Db::getInstance()->insert('dydaps_pack_cart', $payload);
    }

    /**
     * Return all configured pack rows for a cart.
     *
     * @param int $idCart cart identifier
     *
     * @return list<array<string, mixed>>
     */
    public function getCartConfigurations(int $idCart): array
    {
        return \Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart` WHERE id_cart = ' . (int) $idCart) ?: [];
    }

    /**
     * Return one stored pack configuration by native customization id.
     *
     * @param int $idCart cart identifier
     * @param int $idCustomization native customization identifier
     *
     * @return array<string, mixed>|null stored module cart row
     */
    public function getCartConfigurationByCustomization(int $idCart, int $idCustomization): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart`
            WHERE id_cart = ' . (int) $idCart . ' AND id_customization = ' . (int) $idCustomization
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Update the synchronized module quantity for one native customization.
     *
     * @param int $idCart cart identifier
     * @param int $idCustomization native customization identifier
     * @param int $quantity native cart line quantity
     *
     * @return bool true when the row is updated
     */
    public function updateQuantityByCustomization(int $idCart, int $idCustomization, int $quantity): bool
    {
        return \Db::getInstance()->update(
            'dydaps_pack_cart',
            [
                'quantity' => max(1, $quantity),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id_cart = ' . (int) $idCart . ' AND id_customization = ' . (int) $idCustomization
        );
    }

    /**
     * Delete one stored configuration by native customization id.
     *
     * @param int $idCart cart identifier
     * @param int $idCustomization native customization identifier
     *
     * @return bool true when the delete query succeeds
     */
    public function deleteByCustomization(int $idCart, int $idCustomization): bool
    {
        return \Db::getInstance()->delete(
            'dydaps_pack_cart',
            'id_cart = ' . (int) $idCart . ' AND id_customization = ' . (int) $idCustomization
        );
    }

    /**
     * Delete every stored configuration for a cart.
     *
     * @param int $idCart cart identifier
     *
     * @return bool true when the delete query succeeds
     */
    public function deleteByCart(int $idCart): bool
    {
        return \Db::getInstance()->delete('dydaps_pack_cart', 'id_cart = ' . (int) $idCart);
    }

    /**
     * Delete a native cart line by customization without invoking cart hooks.
     *
     * @param int $idCart cart identifier
     * @param int $idProduct pack product identifier
     * @param int $idProductAttribute pack product attribute identifier
     * @param int $idCustomization native customization identifier
     *
     * @return bool true when the delete query succeeds
     */
    public function deleteNativeCartLine(int $idCart, int $idProduct, int $idProductAttribute, int $idCustomization): bool
    {
        if ($idCart <= 0 || $idProduct <= 0 || $idCustomization <= 0) {
            return true;
        }

        return \Db::getInstance()->delete(
            'cart_product',
            'id_cart = ' . (int) $idCart . '
            AND id_product = ' . (int) $idProduct . '
            AND id_product_attribute = ' . (int) $idProductAttribute . '
            AND id_customization = ' . (int) $idCustomization
        );
    }

    /**
     * Remove every row created for a failed native cart add.
     *
     * @param int $idCart cart identifier
     * @param int $idProduct pack product identifier
     * @param int $idProductAttribute pack product attribute identifier
     * @param int $idCustomization native customization identifier
     *
     * @return bool true when all cleanup queries succeed
     */
    public function rollbackNativeAdd(int $idCart, int $idProduct, int $idProductAttribute, int $idCustomization): bool
    {
        return $this->deleteByCustomization($idCart, $idCustomization)
            && $this->deleteNativeCartLine($idCart, $idProduct, $idProductAttribute, $idCustomization)
            && $this->deleteNativeCustomization($idCustomization);
    }

    /**
     * Delete a native customization row created only to split pack cart lines.
     *
     * @param int $idCustomization native customization identifier
     *
     * @return bool true when the delete query succeeds
     */
    public function deleteNativeCustomization(int $idCustomization): bool
    {
        if ($idCustomization <= 0) {
            return true;
        }

        return \Db::getInstance()->delete('customized_data', 'id_customization = ' . (int) $idCustomization)
            && \Db::getInstance()->delete('customization', 'id_customization = ' . (int) $idCustomization);
    }

    /**
     * Return a stored row by cart/product/hash.
     *
     * @param int $idCart cart identifier
     * @param int $idProduct pack product identifier
     * @param int $idProductAttribute pack product attribute identifier
     * @param string $hash configuration hash
     *
     * @return array<string, mixed>|null stored module cart row
     */
    public function getCartConfigurationByHash(int $idCart, int $idProduct, int $idProductAttribute, string $hash): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart`
            WHERE id_cart = ' . (int) $idCart . '
            AND id_product = ' . (int) $idProduct . '
            AND id_product_attribute = ' . (int) $idProductAttribute . '
            AND configuration_hash = "' . pSQL($hash) . '"'
        );

        return is_array($row) ? $row : null;
    }
}
