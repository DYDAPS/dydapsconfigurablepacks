<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackConfiguration
{
    private int $idProduct;
    private int $quantity;
    /** @var array<int,array<string,int>> */
    private array $components;

    /**
     * @param array<int,array<string,int>> $components
     */
    public function __construct(int $idProduct, array $components, int $quantity = 1)
    {
        $this->idProduct = $idProduct;
        $this->components = $components;
        $this->quantity = max(1, $quantity);
    }

    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return array<int,array<string,int>>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * @return array<string,mixed>
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
