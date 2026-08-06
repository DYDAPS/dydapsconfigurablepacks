<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackCartRepository
{
    public function saveConfiguration(int $idCart, int $idProduct, int $idProductAttribute, string $hash, array $configuration, array $price): bool
    {
        $existingId = (int) \Db::getInstance()->getValue(
            'SELECT id_cart_pack FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart`
            WHERE id_cart = ' . (int) $idCart . ' AND id_product = ' . (int) $idProduct . '
            AND id_product_attribute = ' . (int) $idProductAttribute . ' AND configuration_hash = "' . pSQL($hash) . '"'
        );

        $payload = [
            'id_cart' => $idCart,
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'configuration_hash' => pSQL($hash),
            'configuration_json' => pSQL(json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'unit_price_tax_excl' => (float) ($price['unit_tax_excl'] ?? 0),
            'unit_price_tax_incl' => (float) ($price['unit_tax_incl'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existingId > 0) {
            return \Db::getInstance()->update('dydaps_pack_cart', $payload, 'id_cart_pack = ' . $existingId);
        }

        $payload['created_at'] = date('Y-m-d H:i:s');

        return \Db::getInstance()->insert('dydaps_pack_cart', $payload);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getCartConfigurations(int $idCart): array
    {
        return \Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_cart` WHERE id_cart = ' . (int) $idCart) ?: [];
    }
}
