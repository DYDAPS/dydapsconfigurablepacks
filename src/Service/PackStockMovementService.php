<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackStockRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackStockMovementService
{
    private PackOrderRepository $orderRepository;
    private PackStockRepository $stockRepository;

    public function __construct(PackOrderRepository $orderRepository, PackStockRepository $stockRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->stockRepository = $stockRepository;
    }

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
