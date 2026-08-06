<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function getPackByProduct(int $idProduct, int $idShop): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack` WHERE id_product = ' . (int) $idProduct . ' AND id_shop = ' . (int) $idShop
        );

        return is_array($row) ? $row : null;
    }

    public function isPackProduct(int $idProduct, int $idShop): bool
    {
        $pack = $this->getPackByProduct($idProduct, $idShop);

        return $pack !== null && (int) $pack['active'] === 1;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getPack(int $idPack): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack` WHERE id_pack = ' . (int) $idPack . ' AND deleted_at IS NULL'
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
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
     * @return array<int,array<string,mixed>>
     */
    public function getAllowedSelections(int $idComponent): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_component_product`
            WHERE id_component = ' . (int) $idComponent . ' AND active = 1
            ORDER BY position ASC, id_component_product ASC'
        ) ?: [];
    }

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

            return $idPack;
        }

        $payload['created_at'] = date('Y-m-d H:i:s');
        \Db::getInstance()->insert('dydaps_pack', $payload);

        return (int) \Db::getInstance()->Insert_ID();
    }

    public function deletePack(int $idPack): bool
    {
        return \Db::getInstance()->update('dydaps_pack', ['deleted_at' => date('Y-m-d H:i:s'), 'active' => 0], 'id_pack = ' . (int) $idPack);
    }
}
