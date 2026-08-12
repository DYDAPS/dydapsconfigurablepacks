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
 * Wraps PrestaShop stock reads/writes and module stock operation logging.
 */
class PackStockRepository
{
    /**
     * Return available stock for a product or combination in a shop.
     *
     * @param int $idProduct product identifier
     * @param int $idProductAttribute combination identifier, or zero for product stock
     * @param int $idShop shop identifier
     *
     * @return int available quantity reported by PrestaShop
     */
    public function getAvailableQuantity(int $idProduct, int $idProductAttribute, int $idShop): int
    {
        return (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute, $idShop);
    }

    /**
     * Decrease PrestaShop available stock by the absolute quantity.
     *
     * @param int $idProduct product identifier
     * @param int $idProductAttribute combination identifier, or zero for product stock
     * @param int $idShop shop identifier
     * @param int $quantity quantity to decrement
     *
     * @return void
     */
    public function decrement(int $idProduct, int $idProductAttribute, int $idShop, int $quantity): void
    {
        \StockAvailable::updateQuantity($idProduct, $idProductAttribute, -abs($quantity), $idShop);
    }

    /**
     * Increase PrestaShop available stock by the absolute quantity.
     *
     * @param int $idProduct product identifier
     * @param int $idProductAttribute combination identifier, or zero for product stock
     * @param int $idShop shop identifier
     * @param int $quantity quantity to restore
     *
     * @return void
     */
    public function restore(int $idProduct, int $idProductAttribute, int $idShop, int $quantity): void
    {
        \StockAvailable::updateQuantity($idProduct, $idProductAttribute, abs($quantity), $idShop);
    }

    /**
     * Record an idempotent stock operation.
     *
     * @param array{
     *     operation_key: string,
     *     operation_type: string,
     *     id_order?: int,
     *     id_pack_order?: int,
     *     id_product: int,
     *     id_product_attribute?: int,
     *     id_shop: int,
     *     quantity_delta: int
     * } $operation
     *
     * @return bool true only when the operation key was inserted for the first time
     */
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
