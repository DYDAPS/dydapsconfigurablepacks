<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Persists immutable order snapshots for configured packs and components.
 */
final class PackOrderRepository
{
    /**
     * Create the parent order snapshot for one configured pack line.
     *
     * @param array{
     *     id_order: int,
     *     id_order_detail?: int,
     *     id_cart: int,
     *     id_customization?: int,
     *     id_pack: int,
     *     id_product: int,
     *     id_shop: int,
     *     id_lang: int,
     *     id_currency: int,
     *     configuration_hash: string,
     *     pack_name: string,
     *     product_reference?: string,
     *     quantity: int,
     *     unit_price_tax_excl: float,
     *     unit_price_tax_incl: float,
     *     total_price_tax_excl: float,
     *     total_price_tax_incl: float
     * }&array<string, mixed> $snapshot Immutable order snapshot payload.
     *
     * @return int Created dydaps_pack_order identifier.
     *
     * @throws \RuntimeException When the native order detail is missing.
     */
    public function createSnapshot(array $snapshot): int
    {
        if ((int) ($snapshot['id_order_detail'] ?? 0) <= 0) {
            throw new \RuntimeException('Pack order snapshot requires a valid native order detail.');
        }

        $existingId = $this->findExistingSnapshotId(
            (int) $snapshot['id_order'],
            (int) $snapshot['id_cart'],
            (int) ($snapshot['id_customization'] ?? 0),
            (string) $snapshot['configuration_hash']
        );
        if ($existingId > 0) {
            return $existingId;
        }

        \Db::getInstance()->insert('dydaps_pack_order', [
            'id_order' => (int) $snapshot['id_order'],
            'id_order_detail' => (int) ($snapshot['id_order_detail'] ?? 0),
            'id_cart' => (int) $snapshot['id_cart'],
            'id_customization' => (int) ($snapshot['id_customization'] ?? 0),
            'id_pack' => (int) $snapshot['id_pack'],
            'id_product' => (int) $snapshot['id_product'],
            'id_shop' => (int) $snapshot['id_shop'],
            'id_lang' => (int) $snapshot['id_lang'],
            'id_currency' => (int) $snapshot['id_currency'],
            'configuration_hash' => pSQL((string) $snapshot['configuration_hash']),
            'pack_name' => pSQL((string) $snapshot['pack_name']),
            'product_reference' => pSQL((string) ($snapshot['product_reference'] ?? '')),
            'quantity' => (int) $snapshot['quantity'],
            'unit_price_tax_excl' => (float) $snapshot['unit_price_tax_excl'],
            'unit_price_tax_incl' => (float) $snapshot['unit_price_tax_incl'],
            'total_price_tax_excl' => (float) $snapshot['total_price_tax_excl'],
            'total_price_tax_incl' => (float) $snapshot['total_price_tax_incl'],
            'snapshot_json' => pSQL(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }

    /**
     * Return an existing snapshot id for an already transferred cart line.
     *
     * @param int $idOrder Order identifier.
     * @param int $idCart Cart identifier.
     * @param int $idCustomization Native customization identifier.
     * @param string $configurationHash Stable configuration hash.
     *
     * @return int Existing snapshot identifier or zero.
     */
    public function findExistingSnapshotId(int $idOrder, int $idCart, int $idCustomization, string $configurationHash): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT id_pack_order FROM `' . _DB_PREFIX_ . 'dydaps_pack_order`
            WHERE id_order = ' . (int) $idOrder . '
            AND id_cart = ' . (int) $idCart . '
            AND id_customization = ' . (int) $idCustomization . '
            AND configuration_hash = "' . pSQL($configurationHash) . '"'
        );
    }

    /**
     * Create a component snapshot row for a pack order snapshot.
     *
     * @param int $idPackOrder Configured pack order snapshot identifier.
     * @param array<string, mixed> $component Component allocation and catalog snapshot data.
     *
     * @return bool True when the component snapshot row is inserted.
     */
    public function createComponentSnapshot(int $idPackOrder, array $component): bool
    {
        return \Db::getInstance()->insert('dydaps_pack_order_component', [
            'id_pack_order' => $idPackOrder,
            'id_component' => (int) ($component['id_component'] ?? 0),
            'id_product' => (int) ($component['id_product'] ?? 0),
            'id_product_attribute' => (int) ($component['id_product_attribute'] ?? 0),
            'component_name' => pSQL((string) ($component['component_name'] ?? '')),
            'product_name' => pSQL((string) ($component['product_name'] ?? '')),
            'product_reference' => pSQL((string) ($component['product_reference'] ?? '')),
            'combination_reference' => pSQL((string) ($component['combination_reference'] ?? '')),
            'attributes_text' => pSQL((string) ($component['attributes_text'] ?? '')),
            'quantity_per_pack' => (int) ($component['quantity_per_pack'] ?? 1),
            'quantity_total' => (int) ($component['quantity_total'] ?? 1),
            'unit_price_tax_excl' => (float) ($component['unit_price_tax_excl'] ?? 0),
            'unit_price_tax_incl' => (float) ($component['unit_price_tax_incl'] ?? 0),
            'tax_rate' => (float) ($component['tax_rate'] ?? 0),
            'allocated_discount_tax_excl' => (float) ($component['allocated_discount_tax_excl'] ?? 0),
            'allocated_discount_tax_incl' => (float) ($component['allocated_discount_tax_incl'] ?? 0),
            'refundable_tax_excl' => (float) ($component['refundable_tax_excl'] ?? 0),
            'refundable_tax_incl' => (float) ($component['refundable_tax_incl'] ?? 0),
        ]);
    }

    /**
     * Return configured pack snapshots for an order.
     *
     * @param int $idOrder Order identifier.
     *
     * @return list<array<string, mixed>>
     */
    public function getOrderSnapshots(int $idOrder): array
    {
        return \Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order` WHERE id_order = ' . (int) $idOrder) ?: [];
    }

    /**
     * Return one configured pack order snapshot.
     *
     * @param int $idPackOrder Configured pack order snapshot identifier.
     *
     * @return array<string, mixed>|null Snapshot row.
     */
    public function getOrderSnapshot(int $idPackOrder): ?array
    {
        $row = \Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order` WHERE id_pack_order = ' . (int) $idPackOrder);

        return is_array($row) ? $row : null;
    }

    /**
     * Return one configured pack order snapshot by native order detail.
     *
     * @param int $idOrderDetail Native order detail identifier.
     *
     * @return array<string, mixed>|null Snapshot row.
     */
    public function getOrderSnapshotByOrderDetail(int $idOrderDetail): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order`
            WHERE id_order_detail = ' . (int) $idOrderDetail
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Return component snapshot rows for a configured pack order.
     *
     * @param int $idPackOrder Configured pack order snapshot identifier.
     *
     * @return list<array<string, mixed>>
     */
    public function getComponents(int $idPackOrder): array
    {
        return \Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_order_component` WHERE id_pack_order = ' . (int) $idPackOrder) ?: [];
    }

    /**
     * Return refund rows already recorded for a configured pack order.
     *
     * @param int $idPackOrder Configured pack order snapshot identifier.
     *
     * @return list<array<string, mixed>>
     */
    public function getRefunds(int $idPackOrder): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dydaps_pack_refund`
            WHERE id_pack_order = ' . (int) $idPackOrder . '
            ORDER BY id_pack_refund ASC'
        ) ?: [];
    }

    /**
     * Record a pack refund when its operation key has not been seen yet.
     *
     * @param array{
     *     id_order: int,
     *     id_pack_order: int,
     *     id_pack_order_component?: int,
     *     id_order_slip?: int,
     *     operation_key: string,
     *     refund_type: string,
     *     quantity: int,
     *     amount_tax_excl: float,
     *     amount_tax_incl: float,
     *     restocked?: bool|int
     * } $refund Refund row.
     *
     * @return bool True when a new row is inserted, false when the operation already exists.
     */
    public function recordRefund(array $refund): bool
    {
        $operationKey = (string) ($refund['operation_key'] ?? '');
        if ($operationKey === '') {
            throw new \RuntimeException('Pack refund operation key is required.');
        }

        if ((int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dydaps_pack_refund`
            WHERE operation_key = "' . pSQL($operationKey) . '"'
        ) > 0) {
            return false;
        }

        return \Db::getInstance()->insert('dydaps_pack_refund', [
            'id_order' => (int) $refund['id_order'],
            'id_pack_order' => (int) $refund['id_pack_order'],
            'id_pack_order_component' => (int) ($refund['id_pack_order_component'] ?? 0),
            'id_order_slip' => (int) ($refund['id_order_slip'] ?? 0),
            'operation_key' => pSQL($operationKey),
            'refund_type' => pSQL((string) $refund['refund_type']),
            'quantity' => max(1, (int) $refund['quantity']),
            'amount_tax_excl' => (float) $refund['amount_tax_excl'],
            'amount_tax_incl' => (float) $refund['amount_tax_incl'],
            'restocked' => (int) !empty($refund['restocked']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Return the total pack quantity already refunded for a snapshot.
     *
     * @param int $idPackOrder Configured pack order snapshot identifier.
     *
     * @return int Refunded pack quantity.
     */
    public function getRefundedQuantity(int $idPackOrder): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(quantity), 0) FROM `' . _DB_PREFIX_ . 'dydaps_pack_refund`
            WHERE id_pack_order = ' . (int) $idPackOrder
        );
    }

    /**
     * Return whether a snapshot already has component rows.
     *
     * @param int $idPackOrder Configured pack order snapshot identifier.
     *
     * @return bool True when at least one component row exists.
     */
    public function hasComponents(int $idPackOrder): bool
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dydaps_pack_order_component` WHERE id_pack_order = ' . (int) $idPackOrder
        ) > 0;
    }
}
