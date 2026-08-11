<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackCartRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Synchronizes configured packs from a validated cart to an order.
 */
final class PackOrderService
{
    private PackCartRepository $cartRepository;
    private PackCartSynchronizer $cartSynchronizer;
    private PackSnapshotService $snapshotService;
    private PackStockMovementService $stockMovementService;

    /**
     * @param PackCartRepository $cartRepository Repository containing configured cart rows.
     * @param PackCartSynchronizer $cartSynchronizer Service aligning module rows with native cart rows.
     * @param PackSnapshotService $snapshotService Service creating immutable order snapshots.
     * @param PackStockMovementService $stockMovementService Service applying component stock movements.
     *
     * @return void
     */
    public function __construct(PackCartRepository $cartRepository, PackCartSynchronizer $cartSynchronizer, PackSnapshotService $snapshotService, PackStockMovementService $stockMovementService)
    {
        $this->cartRepository = $cartRepository;
        $this->cartSynchronizer = $cartSynchronizer;
        $this->snapshotService = $snapshotService;
        $this->stockMovementService = $stockMovementService;
    }

    /**
     * Create order snapshots for configured cart packs and decrement stock.
     *
     * Side effects: inserts immutable order snapshot rows and stock operation
     * rows, then decrements selected component quantities when each operation is
     * logged for the first time.
     *
     * @param \Order $order Validated PrestaShop order.
     * @param \Cart $cart Source cart containing configured pack rows.
     *
     * @return void
     */
    public function handleValidatedOrder(\Order $order, \Cart $cart): void
    {
        $this->cartSynchronizer->synchronizeCart($cart);
        foreach ($this->cartRepository->getCartConfigurations((int) $cart->id) as $configuration) {
            $idPackOrder = $this->snapshotService->createOrderSnapshot($order, $cart, $configuration);
            $this->stockMovementService->decrementOrderComponents((int) $order->id, $idPackOrder, (int) $order->id_shop);
            $this->stockMovementService->restoreOrderContainerIfNeeded((int) $order->id, $idPackOrder, (int) $order->id_shop);
        }
    }
}
