<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackPrice
{
    public float $unitTaxExcl;
    public float $unitTaxIncl;
    public float $totalTaxExcl;
    public float $totalTaxIncl;
    /** @var array<int,array<string,mixed>> */
    public array $allocations;

    /**
     * @param array<int,array<string,mixed>> $allocations
     */
    public function __construct(float $unitTaxExcl, float $unitTaxIncl, int $quantity, array $allocations)
    {
        $this->unitTaxExcl = $unitTaxExcl;
        $this->unitTaxIncl = $unitTaxIncl;
        $this->totalTaxExcl = $unitTaxExcl * $quantity;
        $this->totalTaxIncl = $unitTaxIncl * $quantity;
        $this->allocations = $allocations;
    }
}
