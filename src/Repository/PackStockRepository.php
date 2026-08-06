<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PackStockRepository
{
    public function getAvailableQuantity(int $idProduct, int $idProductAttribute, int $idShop): int
    {
        return (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop);
    }

    public function decrement(int $idProduct, int $idProductAttribute, int $idShop, int $quantity): void
    {
        \StockAvailable::updateQuantity($idProduct, $idProductAttribute, -abs($quantity), $idShop);
    }

    public function restore(int $idProduct, int $idProductAttribute, int $idShop, int $quantity): void
    {
        \StockAvailable::updateQuantity($idProduct, $idProductAttribute, abs($quantity), $idShop);
    }

    public function logOperation(array $operation): bool
    {
        return \Db::getInstance()->insert('dydaps_pack_stock_operation', [
            'operation_key' => pSQL((string) $operation['operation_key']),
            'operation_type' => pSQL((string) $operation['operation_type']),
            'id_order' => (int) ($operation['id_order'] ?? 0),
            'id_pack_order' => (int) ($operation['id_pack_order'] ?? 0),
            'id_product' => (int) $operation['id_product'],
            'id_product_attribute' => (int) ($operation['id_product_attribute'] ?? 0),
            'id_shop' => (int) $operation['id_shop'],
            'quantity_delta' => (int) $operation['quantity_delta'],
            'created_at' => date('Y-m-d H:i:s'),
        ], false, true, \Db::INSERT_IGNORE);
    }
}
