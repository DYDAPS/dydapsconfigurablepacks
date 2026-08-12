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

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Repository\PackCartRepository;

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
     * @param PackCartRepository $cartRepository repository containing configured cart rows
     * @param PackCartSynchronizer $cartSynchronizer service aligning module rows with native cart rows
     * @param PackSnapshotService $snapshotService service creating immutable order snapshots
     * @param PackStockMovementService $stockMovementService service applying component stock movements
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
     * @param \Order $order validated PrestaShop order
     * @param \Cart $cart source cart containing configured pack rows
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
