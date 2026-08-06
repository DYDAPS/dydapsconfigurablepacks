<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Creates immutable order snapshots for configured packs.
 *
 * Snapshots capture the selected components, current catalog prices, allocated
 * discounts and refundable amounts at validation time so later catalog changes
 * do not alter order/refund calculations.
 */
final class PackSnapshotService
{
    private PackRepository $packRepository;
    private PackOrderRepository $orderRepository;
    private PackPriceCalculator $priceCalculator;

    /**
     * @param PackRepository $packRepository Repository used to reload the pack definition.
     * @param PackOrderRepository $orderRepository Repository used to persist snapshots.
     * @param PackPriceCalculator $priceCalculator Calculator used to snapshot current prices.
     *
     * @return void
     */
    public function __construct(PackRepository $packRepository, PackOrderRepository $orderRepository, PackPriceCalculator $priceCalculator)
    {
        $this->packRepository = $packRepository;
        $this->orderRepository = $orderRepository;
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * Persist one pack order snapshot and its component snapshot rows.
     *
     * @param \Order $order Validated order receiving the snapshot.
     * @param \Cart $cart Source cart for the configured pack.
     * @param array{
     *     configuration_hash: string,
     *     configuration_json: string
     * }&array<string, mixed> $cartConfiguration Row loaded from dydaps_pack_cart.
     *
     * @return int Created dydaps_pack_order identifier.
     *
     * @throws \RuntimeException When the stored cart JSON is invalid or the pack definition no longer exists.
     */
    public function createOrderSnapshot(\Order $order, \Cart $cart, array $cartConfiguration): int
    {
        $configurationData = json_decode((string) $cartConfiguration['configuration_json'], true);
        if (!is_array($configurationData)) {
            throw new \RuntimeException('Invalid pack configuration snapshot.');
        }

        $configuration = new PackConfiguration((int) $configurationData['id_product'], (array) $configurationData['components'], (int) ($configurationData['quantity'] ?? 1));
        $pack = $this->packRepository->getPackByProduct((int) $configurationData['id_product'], (int) $order->id_shop);
        if (!$pack) {
            throw new \RuntimeException('Pack definition no longer exists.');
        }

        $price = $this->priceCalculator->calculate($configuration, (int) $order->id_shop, (int) $order->id_lang, (int) $order->id_currency, (int) $order->id_customer);
        $product = new \Product($configuration->getIdProduct(), false, (int) $order->id_lang, (int) $order->id_shop);

        $snapshot = [
            'id_order' => (int) $order->id,
            'id_cart' => (int) $cart->id,
            'id_pack' => (int) $pack['id_pack'],
            'id_product' => $configuration->getIdProduct(),
            'id_shop' => (int) $order->id_shop,
            'id_lang' => (int) $order->id_lang,
            'id_currency' => (int) $order->id_currency,
            'configuration_hash' => (string) $cartConfiguration['configuration_hash'],
            'pack_name' => (string) $product->name,
            'product_reference' => (string) $product->reference,
            'quantity' => $configuration->getQuantity(),
            'unit_price_tax_excl' => $price->unitTaxExcl,
            'unit_price_tax_incl' => $price->unitTaxIncl,
            'total_price_tax_excl' => $price->totalTaxExcl,
            'total_price_tax_incl' => $price->totalTaxIncl,
            'components' => $price->allocations,
        ];

        $idPackOrder = $this->orderRepository->createSnapshot($snapshot);
        foreach ($price->allocations as $component) {
            $component['quantity_total'] = (int) $component['quantity_per_pack'] * $configuration->getQuantity();
            // Refundable amounts are stored after discount allocation so later
            // partial refunds can reproduce the original tax/discount split.
            $component['refundable_tax_excl'] = max(0.0, (float) $component['total_tax_excl'] - (float) ($component['allocated_discount_tax_excl'] ?? 0));
            $component['refundable_tax_incl'] = max(0.0, (float) $component['total_tax_incl'] - (float) ($component['allocated_discount_tax_incl'] ?? 0));
            $this->orderRepository->createComponentSnapshot($idPackOrder, $component);
        }

        return $idPackOrder;
    }
}
