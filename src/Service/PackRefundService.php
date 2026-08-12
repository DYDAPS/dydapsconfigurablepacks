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

use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\VoucherRefundType;

/**
 * Calculates and records refunds for configured pack order snapshots.
 */
final class PackRefundService
{
    private PackOrderRepository $orderRepository;
    private PackStockMovementService $stockMovementService;
    private \Context $context;

    /**
     * @param PackOrderRepository $orderRepository repository used to read order snapshots and record refunds
     * @param PackStockMovementService $stockMovementService service used to restore component stock
     * @param \Context $context injected legacy context used to resolve the Symfony container on older shops
     *
     * @return void
     */
    public function __construct(PackOrderRepository $orderRepository, PackStockMovementService $stockMovementService, \Context $context)
    {
        $this->orderRepository = $orderRepository;
        $this->stockMovementService = $stockMovementService;
        $this->context = $context;
    }

    /**
     * Refund a whole or partial quantity of a configured pack.
     *
     * The refund amount is proportional to the immutable order snapshot totals,
     * not to current catalog prices.
     *
     * @param int $idOrder order identifier
     * @param int $idPackOrder configured pack order snapshot identifier
     * @param int $idShop shop identifier used for stock restoration
     * @param int $quantity pack quantity to refund
     * @param bool $restoreStock whether component stock should be restored
     *
     * @return array{tax_excl: float, tax_incl: float} refund amounts in the order currency
     *
     * @throws \RuntimeException when the pack order snapshot cannot be found or the requested quantity is no longer refundable
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
     * Refund a historical component amount through PrestaShop's native pack order detail.
     *
     * Native accounting remains attached to the pack order detail. The component
     * row records the descriptive split while the native partial refund command
     * creates the credit slip and updates PrestaShop order refund state.
     *
     * @param int $idOrder order identifier
     * @param int $idPackOrderComponent component snapshot identifier
     * @param int $quantity component quantity to refund
     * @param bool $restoreStock whether only this component stock should be restored
     * @param bool $generateCreditSlip whether the native refund must create a credit slip
     *
     * @return array{tax_excl: float, tax_incl: float, native_pack_quantity: int} refund amounts in the order currency
     *
     * @throws \RuntimeException when the component is not refundable or the native command bus is unavailable
     */
    public function refundComponent(int $idOrder, int $idPackOrderComponent, int $quantity, bool $restoreStock, bool $generateCreditSlip): array
    {
        if (!$generateCreditSlip) {
            throw new \RuntimeException('A native credit slip is required for component refunds.');
        }

        $component = $this->orderRepository->getComponent($idPackOrderComponent);
        if (!$component) {
            throw new \RuntimeException('Pack component snapshot not found.');
        }

        $idPackOrder = (int) $component['id_pack_order'];
        $snapshot = $this->orderRepository->getOrderSnapshot($idPackOrder);
        if (!$snapshot || (int) $snapshot['id_order'] !== $idOrder || (int) $snapshot['id_order_detail'] <= 0) {
            throw new \RuntimeException('Pack order snapshot not found.');
        }

        $orderedComponentQuantity = max(0, (int) $component['quantity_total']);
        $alreadyComponentRefunded = $this->orderRepository->getComponentRefundedQuantity($idPackOrderComponent);
        $remainingComponentQuantity = $orderedComponentQuantity - $alreadyComponentRefunded;
        if ($quantity <= 0 || $quantity > $remainingComponentQuantity) {
            throw new \RuntimeException('The requested component refund quantity exceeds the remaining refundable quantity.');
        }

        $quantityPerPack = max(1, (int) $component['quantity_per_pack']);
        $nativePackQuantity = (int) ceil($quantity / $quantityPerPack);
        $orderedPackQuantity = max(1, (int) $snapshot['quantity']);
        $alreadyNativeRefunded = $this->orderRepository->getRefundedQuantity($idPackOrder);
        if ($nativePackQuantity <= 0 || $nativePackQuantity > ($orderedPackQuantity - $alreadyNativeRefunded)) {
            throw new \RuntimeException('PrestaShop cannot represent this component refund because the native pack line has no remaining refundable quantity.');
        }

        $amounts = $this->calculateComponentRefundAmounts($component, $quantity);
        $amountTaxIncl = number_format($amounts['tax_incl'], 6, '.', '');
        $operationKey = sprintf(
            'module-component:%d:%d:%d:%d:%0.6F:%s',
            $idOrder,
            $idPackOrderComponent,
            $quantity,
            $alreadyComponentRefunded,
            $amounts['tax_incl'],
            $restoreStock ? 'restock' : 'no-restock'
        );

        $this->issueNativePartialRefund(
            (int) $snapshot['id_order'],
            (int) $snapshot['id_order_detail'],
            $nativePackQuantity,
            $amountTaxIncl,
            $generateCreditSlip,
            true
        );

        $this->stockMovementService->neutralizeContainerRestockQuantity(
            (int) $snapshot['id_order'],
            $idPackOrder,
            (int) $snapshot['id_shop'],
            $nativePackQuantity,
            sha1($operationKey)
        );

        if ($restoreStock) {
            $this->stockMovementService->restoreOrderComponent(
                (int) $snapshot['id_order'],
                $idPackOrder,
                $idPackOrderComponent,
                (int) $snapshot['id_shop'],
                $quantity,
                sha1($operationKey)
            );
        }

        $this->orderRepository->recordRefund([
            'id_order' => (int) $snapshot['id_order'],
            'id_pack_order' => $idPackOrder,
            'id_pack_order_component' => $idPackOrderComponent,
            'operation_key' => $operationKey,
            'refund_type' => 'component',
            'quantity' => $quantity,
            'amount_tax_excl' => $amounts['tax_excl'],
            'amount_tax_incl' => $amounts['tax_incl'],
            'restocked' => $restoreStock,
        ]);

        return [
            'tax_excl' => $amounts['tax_excl'],
            'tax_incl' => $amounts['tax_incl'],
            'native_pack_quantity' => $nativePackQuantity,
        ];
    }

    /**
     * Record a refund emitted by PrestaShop's native order detail workflow.
     *
     * @param int $idOrderDetail native order detail identifier
     * @param int $quantity refunded pack quantity
     * @param float $amount amount provided by PrestaShop's native refund hook
     * @param bool $restoreStock whether PrestaShop reinjected stock for this refund
     * @param string $action native cancellation action type
     *
     * @return bool true when the native refund was recorded for a pack snapshot
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
        if ($amount > 0.0 && $amount <= $historicalTaxIncl) {
            $historicalTaxIncl = round($amount, 6);
            $amountRatio = $historicalTaxIncl / max(0.000001, (float) $snapshot['total_price_tax_incl']);
            $historicalTaxExcl = round((float) $snapshot['total_price_tax_excl'] * $amountRatio, 6);
        }
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
            $this->stockMovementService->neutralizeContainerRestockQuantity(
                (int) $snapshot['id_order'],
                (int) $snapshot['id_pack_order'],
                (int) $snapshot['id_shop'],
                $quantity,
                sha1($operationKey)
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

    /**
     * Calculate the historical refundable component amounts.
     *
     * @param array<string, mixed> $component component snapshot row
     * @param int $quantity component quantity to refund
     *
     * @return array{tax_excl: float, tax_incl: float} monetary amounts in the order currency
     */
    private function calculateComponentRefundAmounts(array $component, int $quantity): array
    {
        $orderedQuantity = max(1, (int) $component['quantity_total']);
        $ratio = $quantity / $orderedQuantity;

        return [
            'tax_excl' => round((float) $component['refundable_tax_excl'] * $ratio, 6),
            'tax_incl' => round((float) $component['refundable_tax_incl'] * $ratio, 6),
        ];
    }

    /**
     * Execute PrestaShop's native partial refund command.
     *
     * @param int $idOrder order identifier
     * @param int $idOrderDetail native pack order detail identifier
     * @param int $nativePackQuantity native pack quantity consumed by the refund
     * @param string $amountTaxIncl historical tax-included amount to refund
     * @param bool $generateCreditSlip whether PrestaShop should create a credit slip
     *
     * @return void
     */
    private function issueNativePartialRefund(int $idOrder, int $idOrderDetail, int $nativePackQuantity, string $amountTaxIncl, bool $generateCreditSlip, bool $suppressGenericComponentRestore = false): void
    {
        if (!class_exists(IssuePartialRefundCommand::class) || !class_exists(VoucherRefundType::class)) {
            throw new \RuntimeException('Native partial refund commands are not available in this PrestaShop version.');
        }

        $container = SymfonyContainer::getInstance();
        if (!$container || !$container->has('prestashop.core.command_bus')) {
            $context = class_exists('\Context') ? $this->context : null;
            $container = $context && isset($context->container) ? $context->container : null;
        }
        if (!$container || !$container->has('prestashop.core.command_bus')) {
            throw new \RuntimeException('PrestaShop command bus is required for native component refunds.');
        }

        if ($suppressGenericComponentRestore) {
            $GLOBALS['DYDAPS_CONFIGURABLE_PACKS_COMPONENT_REFUND_ORDER_DETAIL'] = $idOrderDetail;
        }

        try {
            $container->get('prestashop.core.command_bus')->handle(new IssuePartialRefundCommand(
                $idOrder,
                [
                    $idOrderDetail => [
                        'quantity' => $nativePackQuantity,
                        'amount' => $amountTaxIncl,
                    ],
                ],
                '0',
                false,
                $generateCreditSlip,
                false,
                VoucherRefundType::PRODUCT_PRICES_REFUND
            ));
        } finally {
            if ($suppressGenericComponentRestore) {
                unset($GLOBALS['DYDAPS_CONFIGURABLE_PACKS_COMPONENT_REFUND_ORDER_DETAIL']);
            }
        }
    }
}
