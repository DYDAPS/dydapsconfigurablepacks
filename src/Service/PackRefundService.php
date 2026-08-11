<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Calculates and records refunds for configured pack order snapshots.
 */
final class PackRefundService
{
    private PackOrderRepository $orderRepository;
    private PackStockMovementService $stockMovementService;

    /**
     * @param PackOrderRepository $orderRepository Repository used to read order snapshots and record refunds.
     * @param PackStockMovementService $stockMovementService Service used to restore component stock.
     *
     * @return void
     */
    public function __construct(PackOrderRepository $orderRepository, PackStockMovementService $stockMovementService)
    {
        $this->orderRepository = $orderRepository;
        $this->stockMovementService = $stockMovementService;
    }

    /**
     * Refund a whole or partial quantity of a configured pack.
     *
     * The refund amount is proportional to the immutable order snapshot totals,
     * not to current catalog prices.
     *
     * @param int $idOrder Order identifier.
     * @param int $idPackOrder Configured pack order snapshot identifier.
     * @param int $idShop Shop identifier used for stock restoration.
     * @param int $quantity Pack quantity to refund.
     * @param bool $restoreStock Whether component stock should be restored.
     *
     * @return array{tax_excl: float, tax_incl: float} Refund amounts in the order currency.
     *
     * @throws \RuntimeException When the pack order snapshot cannot be found or the requested quantity is no longer refundable.
     */
    public function refundPack(int $idOrder, int $idPackOrder, int $idShop, int $quantity, bool $restoreStock): array
    {
        $snapshots = $this->orderRepository->getOrderSnapshots($idOrder);
        $snapshot = null;
        foreach ($snapshots as $row) {
            if ((int) $row['id_pack_order'] === $idPackOrder) {
                $snapshot = $row;
                break;
            }
        }
        if (!$snapshot) {
            throw new \RuntimeException('Pack order snapshot not found.');
        }

        $orderedQuantity = max(1, (int) $snapshot['quantity']);
        $alreadyRefunded = $this->orderRepository->getRefundedQuantity($idPackOrder);
        $remainingQuantity = $orderedQuantity - $alreadyRefunded;
        if ($quantity <= 0 || $quantity > $remainingQuantity) {
            throw new \RuntimeException('The requested pack refund quantity exceeds the remaining refundable quantity.');
        }

        $ratio = $quantity / max(1, (int) $snapshot['quantity']);
        $amounts = [
            'tax_excl' => round((float) $snapshot['total_price_tax_excl'] * $ratio, 6),
            'tax_incl' => round((float) $snapshot['total_price_tax_incl'] * $ratio, 6),
        ];

        if ($restoreStock) {
            $this->stockMovementService->restoreOrderComponents($idOrder, $idPackOrder, $idShop, $quantity);
        }

        \Db::getInstance()->insert('dydaps_pack_refund', [
            'id_order' => $idOrder,
            'id_pack_order' => $idPackOrder,
            'refund_type' => 'pack',
            'quantity' => $quantity,
            'amount_tax_excl' => $amounts['tax_excl'],
            'amount_tax_incl' => $amounts['tax_incl'],
            'restocked' => (int) $restoreStock,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $amounts;
    }
}
