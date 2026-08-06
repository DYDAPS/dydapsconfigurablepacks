<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Represents a customer's selected configuration for one configurable pack.
 *
 * Component entries use PrestaShop product identifiers and optional combination
 * identifiers. Quantities are per pack unit; the object-level quantity is the
 * number of configured packs added to the cart.
 */
final class PackConfiguration
{
    private int $idProduct;
    private int $quantity;

    /**
     * @var list<array{
     *     id_component: int,
     *     id_product: int,
     *     id_product_attribute?: int,
     *     quantity?: int
     * }>
     */
    private array $components;

    /**
     * @param list<array{
     *     id_component: int,
     *     id_product: int,
     *     id_product_attribute?: int,
     *     quantity?: int
     * }> $components Selected component products, with quantities per pack unit.
     * @param int $idProduct Native PrestaShop product sold as the pack container.
     * @param int $quantity Requested pack quantity.
     *
     * @return void
     */
    public function __construct(int $idProduct, array $components, int $quantity = 1)
    {
        $this->idProduct = $idProduct;
        $this->components = $components;
        $this->quantity = max(1, $quantity);
    }

    /**
     * Return the native PrestaShop product sold as the pack container.
     *
     * @return int Product identifier.
     */
    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    /**
     * Return the number of configured packs requested.
     *
     * @return int Quantity normalized to at least one.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Return selected component products.
     *
     * @return list<array{
     *     id_component: int,
     *     id_product: int,
     *     id_product_attribute?: int,
     *     quantity?: int
     * }>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Serialize the configuration for hashing and cart persistence.
     *
     * @return array{
     *     id_product: int,
     *     quantity: int,
     *     components: list<array{
     *         id_component: int,
     *         id_product: int,
     *         id_product_attribute?: int,
     *         quantity?: int
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'id_product' => $this->idProduct,
            'quantity' => $this->quantity,
            'components' => $this->components,
        ];
    }
}
