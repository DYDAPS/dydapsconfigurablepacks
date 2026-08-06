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
     */
    public function createSnapshot(array $snapshot): int
    {
        \Db::getInstance()->insert('dydaps_pack_order', [
            'id_order' => (int) $snapshot['id_order'],
            'id_order_detail' => (int) ($snapshot['id_order_detail'] ?? 0),
            'id_cart' => (int) $snapshot['id_cart'],
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
}
