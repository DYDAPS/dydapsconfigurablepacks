<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Applies stock movements for configured pack components.
 *
 * Stock movements are guarded by idempotent operation keys so repeated order
 * hook execution does not decrement or restore the same component twice.
 */
final class PackStockMovementService
{
    private PackOrderRepository $orderRepository;
    private PackStockRepository $stockRepository;

    /**
     * @param PackOrderRepository $orderRepository Repository used to load component snapshots.
     * @param PackStockRepository $stockRepository Repository used to log and apply stock movements.
     *
     * @return void
     */
    public function __construct(PackOrderRepository $orderRepository, PackStockRepository $stockRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->stockRepository = $stockRepository;
    }

    /**
     * Decrement stock for all component snapshot rows of a configured pack.
     *
     * Side effects: inserts stock operation log rows and decreases PrestaShop
     * available stock only when a log row is newly created.
     *
     * @param int $idOrder Order identifier.
     * @param int $idPackOrder Configured pack order snapshot identifier.
     * @param int $idShop Shop identifier used for stock update.
     *
     * @return void
     */
    public function decrementOrderComponents(int $idOrder, int $idPackOrder, int $idShop): void
    {
        foreach ($this->orderRepository->getComponents($idPackOrder) as $component) {
            $quantity = (int) $component['quantity_total'];
            $key = 'decrement:' . $idOrder . ':' . $idPackOrder . ':' . (int) $component['id_pack_order_component'];
            if ($this->stockRepository->logOperation([
                'operation_key' => $key,
                'operation_type' => 'decrement',
                'id_order' => $idOrder,
                'id_pack_order' => $idPackOrder,
                'id_product' => (int) $component['id_product'],
                'id_product_attribute' => (int) $component['id_product_attribute'],
                'id_shop' => $idShop,
                'quantity_delta' => -$quantity,
            ])) {
                $this->stockRepository->decrement((int) $component['id_product'], (int) $component['id_product_attribute'], $idShop, $quantity);
            }
        }
    }

    /**
     * Restore component stock for a whole or partial pack quantity.
     *
     * @param int $idOrder Order identifier.
     * @param int $idPackOrder Configured pack order snapshot identifier.
     * @param int $idShop Shop identifier used for stock update.
     * @param int $packQuantity Pack quantity to restore. When zero, restores the full quantity_total stored in snapshots.
     *
     * @return void
     */
    public function restoreOrderComponents(int $idOrder, int $idPackOrder, int $idShop, int $packQuantity = 0): void
    {
        foreach ($this->orderRepository->getComponents($idPackOrder) as $component) {
            $quantity = $packQuantity > 0 ? (int) $component['quantity_per_pack'] * $packQuantity : (int) $component['quantity_total'];
            $key = 'restore:' . $idOrder . ':' . $idPackOrder . ':' . (int) $component['id_pack_order_component'] . ':' . $quantity;
            if ($this->stockRepository->logOperation([
                'operation_key' => $key,
                'operation_type' => 'restore',
                'id_order' => $idOrder,
                'id_pack_order' => $idPackOrder,
                'id_product' => (int) $component['id_product'],
                'id_product_attribute' => (int) $component['id_product_attribute'],
                'id_shop' => $idShop,
                'quantity_delta' => $quantity,
            ])) {
                $this->stockRepository->restore((int) $component['id_product'], (int) $component['id_product_attribute'], $idShop, $quantity);
            }
        }
    }
}
