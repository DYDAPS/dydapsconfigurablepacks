<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackCartRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackOrderService
{
    private PackCartRepository $cartRepository;
    private PackSnapshotService $snapshotService;
    private PackStockMovementService $stockMovementService;

    public function __construct(PackCartRepository $cartRepository, PackSnapshotService $snapshotService, PackStockMovementService $stockMovementService)
    {
        $this->cartRepository = $cartRepository;
        $this->snapshotService = $snapshotService;
        $this->stockMovementService = $stockMovementService;
    }

    public function handleValidatedOrder(\Order $order, \Cart $cart): void
    {
        foreach ($this->cartRepository->getCartConfigurations((int) $cart->id) as $configuration) {
            $idPackOrder = $this->snapshotService->createOrderSnapshot($order, $cart, $configuration);
            $this->stockMovementService->decrementOrderComponents((int) $order->id, $idPackOrder, (int) $order->id_shop);
        }
    }
}
