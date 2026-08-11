<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Repository\PackCartRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Keeps module cart rows aligned with native PrestaShop cart/customization rows.
 */
final class PackCartSynchronizer
{
    private PackCartRepository $cartRepository;

    /**
     * @param PackCartRepository $cartRepository Repository used to read and update module cart rows.
     *
     * @return void
     */
    public function __construct(PackCartRepository $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Synchronize every configured pack row for a cart.
     *
     * @param \Cart $cart Native cart instance.
     *
     * @return void
     */
    public function synchronizeCart(\Cart $cart): void
    {
        $idCart = (int) $cart->id;
        if ($idCart <= 0) {
            return;
        }

        $nativeLines = $this->getNativeCustomizedLines($cart);
        $storedRows = $this->cartRepository->getCartConfigurations($idCart);
        if (!$storedRows) {
            return;
        }

        if (!$nativeLines) {
            $this->cartRepository->deleteByCart($idCart);
            foreach ($storedRows as $row) {
                $this->cartRepository->deleteNativeCustomization((int) ($row['id_customization'] ?? 0));
            }

            return;
        }

        foreach ($storedRows as $row) {
            $idCustomization = (int) ($row['id_customization'] ?? 0);
            $native = $nativeLines[$idCustomization] ?? null;
            if ($idCustomization <= 0 || !$native) {
                $this->cartRepository->deleteByCustomization($idCart, $idCustomization);
                $this->cartRepository->deleteNativeCustomization($idCustomization);
                continue;
            }

            $quantity = (int) ($native['cart_quantity'] ?? 0);
            if ($quantity <= 0) {
                $this->cartRepository->deleteByCustomization($idCart, $idCustomization);
                $this->cartRepository->deleteNativeCustomization($idCustomization);
                continue;
            }

            $sameProduct = (int) ($row['id_product'] ?? 0) === (int) ($native['id_product'] ?? 0);
            $sameAttribute = (int) ($row['id_product_attribute'] ?? 0) === (int) ($native['id_product_attribute'] ?? 0);
            if (!$sameProduct || !$sameAttribute) {
                $this->cartRepository->deleteByCustomization($idCart, $idCustomization);
                continue;
            }

            if ((int) ($row['quantity'] ?? 0) !== $quantity) {
                $this->cartRepository->updateQuantityByCustomization($idCart, $idCustomization, $quantity);
            }
        }
    }

    /**
     * Return native customized cart lines indexed by customization id.
     *
     * @param \Cart $cart Native cart instance.
     *
     * @return array<int,array<string,mixed>>
     */
    private function getNativeCustomizedLines(\Cart $cart): array
    {
        $lines = [];
        $rows = \Db::getInstance()->executeS(
            'SELECT id_product, id_product_attribute, id_customization, quantity AS cart_quantity
            FROM `' . _DB_PREFIX_ . 'cart_product`
            WHERE id_cart = ' . (int) $cart->id . ' AND id_customization > 0'
        ) ?: [];

        foreach ($rows as $product) {
            $idCustomization = (int) ($product['id_customization'] ?? 0);
            if ($idCustomization <= 0) {
                continue;
            }

            $lines[$idCustomization] = $product;
        }

        return $lines;
    }
}
