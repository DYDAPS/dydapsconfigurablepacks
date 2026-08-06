<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackRefundService
{
    private PackOrderRepository $orderRepository;
    private PackStockMovementService $stockMovementService;

    public function __construct(PackOrderRepository $orderRepository, PackStockMovementService $stockMovementService)
    {
        $this->orderRepository = $orderRepository;
        $this->stockMovementService = $stockMovementService;
    }

    /**
     * @return array<string,float>
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

        $quantity = min(max(1, $quantity), (int) $snapshot['quantity']);
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
