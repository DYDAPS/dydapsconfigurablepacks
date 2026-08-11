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

        $this->orderRepository->recordRefund([
            'id_order' => $idOrder,
            'id_pack_order' => $idPackOrder,
            'operation_key' => 'module-pack:' . $idOrder . ':' . $idPackOrder . ':' . $quantity . ':' . $alreadyRefunded . ':' . ($restoreStock ? 'restock' : 'no-restock') . ':' . sha1(json_encode($amounts)),
            'refund_type' => 'pack',
            'quantity' => $quantity,
            'amount_tax_excl' => $amounts['tax_excl'],
            'amount_tax_incl' => $amounts['tax_incl'],
            'restocked' => $restoreStock,
        ]);

        return $amounts;
    }

    /**
     * Record a refund emitted by PrestaShop's native order detail workflow.
     *
     * @param int $idOrderDetail Native order detail identifier.
     * @param int $quantity Refunded pack quantity.
     * @param float $amount Amount provided by PrestaShop's native refund hook.
     * @param bool $restoreStock Whether PrestaShop reinjected stock for this refund.
     * @param string $action Native cancellation action type.
     *
     * @return bool True when the native refund was recorded for a pack snapshot.
     */
    public function recordNativeOrderDetailRefund(int $idOrderDetail, int $quantity, float $amount, bool $restoreStock, string $action): bool
    {
        $snapshot = $this->orderRepository->getOrderSnapshotByOrderDetail($idOrderDetail);
        if (!$snapshot) {
            return false;
        }

        $orderedQuantity = max(1, (int) $snapshot['quantity']);
        $alreadyRefunded = $this->orderRepository->getRefundedQuantity((int) $snapshot['id_pack_order']);
        $remainingQuantity = $orderedQuantity - $alreadyRefunded;
        if ($quantity <= 0 || $quantity > $remainingQuantity) {
            throw new \RuntimeException('The native refund quantity exceeds the remaining pack refundable quantity.');
        }

        $ratio = $quantity / $orderedQuantity;
        $historicalTaxIncl = round((float) $snapshot['total_price_tax_incl'] * $ratio, 6);
        $historicalTaxExcl = round((float) $snapshot['total_price_tax_excl'] * $ratio, 6);
        $operationKey = sprintf(
            'native:%d:%d:%s:%d:%0.6F:%d',
            (int) $snapshot['id_order'],
            $idOrderDetail,
            $action,
            $quantity,
            $amount,
            $alreadyRefunded
        );

        if ($restoreStock) {
            $this->stockMovementService->restoreOrderComponents(
                (int) $snapshot['id_order'],
                (int) $snapshot['id_pack_order'],
                (int) $snapshot['id_shop'],
                $quantity
            );
        }

        return $this->orderRepository->recordRefund([
            'id_order' => (int) $snapshot['id_order'],
            'id_pack_order' => (int) $snapshot['id_pack_order'],
            'operation_key' => $operationKey,
            'refund_type' => 'native_' . $action,
            'quantity' => $quantity,
            'amount_tax_excl' => $historicalTaxExcl,
            'amount_tax_incl' => $historicalTaxIncl,
            'restocked' => $restoreStock,
        ]);
    }
}
