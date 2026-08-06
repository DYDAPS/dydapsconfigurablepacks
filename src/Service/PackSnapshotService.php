<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackOrderRepository;
use Dydaps\ConfigurablePacks\Repository\PackRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackSnapshotService
{
    private PackRepository $packRepository;
    private PackOrderRepository $orderRepository;
    private PackPriceCalculator $priceCalculator;

    public function __construct(PackRepository $packRepository, PackOrderRepository $orderRepository, PackPriceCalculator $priceCalculator)
    {
        $this->packRepository = $packRepository;
        $this->orderRepository = $orderRepository;
        $this->priceCalculator = $priceCalculator;
    }

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
            $component['refundable_tax_excl'] = max(0.0, (float) $component['total_tax_excl'] - (float) ($component['allocated_discount_tax_excl'] ?? 0));
            $component['refundable_tax_incl'] = max(0.0, (float) $component['total_tax_incl'] - (float) ($component['allocated_discount_tax_incl'] ?? 0));
            $this->orderRepository->createComponentSnapshot($idPackOrder, $component);
        }

        return $idPackOrder;
    }
}
