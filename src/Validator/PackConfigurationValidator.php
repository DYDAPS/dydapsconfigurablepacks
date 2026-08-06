<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Validator;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackConfigurationValidator
{
    private PackRepository $repository;

    public function __construct(PackRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<int,string>
     */
    public function validate(PackConfiguration $configuration, int $idShop, int $idLang): array
    {
        $errors = [];
        $pack = $this->repository->getPackByProduct($configuration->getIdProduct(), $idShop);
        if (!$pack || (int) $pack['active'] !== 1) {
            return ['The selected product is not an active configurable pack.'];
        }

        $components = $this->repository->getComponents((int) $pack['id_pack'], $idLang);
        $selectedByComponent = [];
        foreach ($configuration->getComponents() as $selection) {
            $selectedByComponent[(int) ($selection['id_component'] ?? 0)] = $selection;
        }

        foreach ($components as $component) {
            $idComponent = (int) $component['id_component'];
            $selection = $selectedByComponent[$idComponent] ?? null;
            if (!$selection && !(int) $component['optional']) {
                $errors[] = 'A required component is missing.';
                continue;
            }
            if (!$selection) {
                continue;
            }

            $allowed = $this->repository->getAllowedSelections($idComponent);
            $valid = false;
            foreach ($allowed as $row) {
                $sameProduct = (int) $row['id_product'] === (int) $selection['id_product'];
                $allowedAttribute = (int) $row['id_product_attribute'];
                $sameAttribute = $allowedAttribute === 0 || $allowedAttribute === (int) ($selection['id_product_attribute'] ?? 0);
                if ($sameProduct && $sameAttribute) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                $errors[] = 'A selected product or combination is not allowed for this pack.';
            }

            $quantity = (int) ($selection['quantity'] ?? $component['quantity']);
            if ($quantity < (int) $component['min_quantity'] || $quantity > (int) $component['max_quantity']) {
                $errors[] = 'A selected component quantity is outside the allowed range.';
            }
        }

        return $errors;
    }
}
