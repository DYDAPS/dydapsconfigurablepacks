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
     *     quantity?: int,
     *     customization?: string,
     *     customization_fields?: list<array{
     *         id_customization_field: int,
     *         value: string
     *     }>
     * }>
     */
    private array $components;

    /**
     * @param list<array{
     *     id_component: int,
     *     id_product: int,
     *     id_product_attribute?: int,
     *     quantity?: int,
     *     customization?: string,
     *     customization_fields?: list<array{
     *         id_customization_field: int,
     *         value: string
     *     }>
     * }> $components Selected component products, with quantities per pack unit
     * @param int $idProduct native PrestaShop product sold as the pack container
     * @param int $quantity requested pack quantity
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
     * @return int product identifier
     */
    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    /**
     * Return the number of configured packs requested.
     *
     * @return int quantity normalized to at least one
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
     *     quantity?: int,
     *     customization?: string,
     *     customization_fields?: list<array{
     *         id_customization_field: int,
     *         value: string
     *     }>
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
     *         quantity?: int,
     *         customization?: string,
     *         customization_fields?: list<array{
     *             id_customization_field: int,
     *             value: string
     *         }>
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
