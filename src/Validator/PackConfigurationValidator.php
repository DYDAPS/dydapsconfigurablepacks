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

namespace Dydaps\ConfigurablePacks\Validator;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dydaps\ConfigurablePacks\Model\PackConfiguration;
use Dydaps\ConfigurablePacks\Repository\PackRepository;

/**
 * Validates a customer pack configuration against the current pack definition.
 */
final class PackConfigurationValidator
{
    private PackRepository $repository;

    /**
     * @param PackRepository $repository repository used to load pack definitions and allowed selections
     *
     * @return void
     */
    public function __construct(PackRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Return validation errors for the selected components.
     *
     * Validation checks that the pack exists and is active in the current shop,
     * required components are selected, selected products/combinations are
     * allowed, and per-component quantities stay within configured bounds.
     *
     * @param PackConfiguration $configuration selected pack configuration
     * @param int $idShop shop identifier used for pack lookup
     * @param int $idLang language identifier used for component lookup
     *
     * @return list<string> english technical errors for callers to translate or normalize
     */
    public function validate(PackConfiguration $configuration, int $idShop, int $idLang): array
    {
        try {
            $this->validateAndNormalize($configuration, $idShop, $idLang);

            return [];
        } catch (\RuntimeException $e) {
            return [$e->getMessage()];
        }
    }

    /**
     * Validate and rebuild a safe configuration from the active pack definition.
     *
     * @param PackConfiguration $configuration customer-submitted configuration
     * @param int $idShop shop identifier used for pack lookup
     * @param int $idLang language identifier used for component lookup
     *
     * @return PackConfiguration safe configuration rebuilt from allowed selections
     *
     * @throws \RuntimeException when the configuration is invalid
     */
    public function validateAndNormalize(PackConfiguration $configuration, int $idShop, int $idLang): PackConfiguration
    {
        $errors = [];
        if ($configuration->getQuantity() <= 0) {
            $errors[] = 'The configured pack quantity is invalid.';
        }

        $pack = $this->repository->getPackByProduct($configuration->getIdProduct(), $idShop);
        if (!$pack || (int) $pack['active'] !== 1) {
            throw new \RuntimeException('The selected product is not an active configurable pack.');
        }

        $components = $this->repository->getComponents((int) $pack['id_pack'], $idLang);
        $componentsById = [];
        foreach ($components as $component) {
            $componentsById[(int) $component['id_component']] = $component;
        }

        $selectedByComponent = [];
        foreach ($configuration->getComponents() as $selection) {
            $idComponent = (int) $selection['id_component'];
            if ($idComponent <= 0 || !isset($componentsById[$idComponent])) {
                $errors[] = 'An unknown component was submitted.';
                continue;
            }
            if (isset($selectedByComponent[$idComponent])) {
                $errors[] = 'A component was submitted more than once.';
                continue;
            }

            $selectedByComponent[$idComponent] = $selection;
        }

        $normalized = [];
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
            $matchedSelection = null;
            foreach ($allowed as $row) {
                $sameProduct = (int) $row['id_product'] === (int) $selection['id_product'];
                $allowedAttribute = (int) $row['id_product_attribute'];
                $sameAttribute = $allowedAttribute === (int) ($selection['id_product_attribute'] ?? 0);
                if ($sameProduct && $sameAttribute) {
                    $valid = true;
                    $matchedSelection = $row;
                    break;
                }
            }
            if (!$valid) {
                $errors[] = 'A selected product or combination is not allowed for this pack.';
                continue;
            }

            $customization = trim((string) ($selection['customization'] ?? ''));
            if ($customization !== '' && !(int) ($component['allow_customization'] ?? 0)) {
                $errors[] = 'Customization is not allowed for this pack component.';
            }

            $quantity = (int) ($selection['quantity'] ?? $component['quantity']);
            if ($quantity <= 0) {
                $errors[] = 'A selected component quantity is invalid.';
                continue;
            }
            if ($quantity < (int) $component['min_quantity'] || $quantity > (int) $component['max_quantity']) {
                $errors[] = 'A selected component quantity is outside the allowed range.';
            }

            $product = new \Product((int) $selection['id_product'], false, $idLang, $idShop);
            if (!\Validate::isLoadedObject($product) || !$this->repository->isProductAvailableInShop((int) $selection['id_product'], $idShop)) {
                $errors[] = 'A selected product is not available in this shop.';
            }
            $idAttribute = (int) ($selection['id_product_attribute'] ?? 0);
            if ($idAttribute > 0) {
                $combination = new \Combination($idAttribute);
                if (!\Validate::isLoadedObject($combination) || (int) $combination->id_product !== (int) $selection['id_product']) {
                    $errors[] = 'A selected combination does not belong to the selected product.';
                }
                if (!$this->repository->isCombinationAvailableInShop($idAttribute, $idShop)) {
                    $errors[] = 'A selected combination is not available in this shop.';
                }
            }

            $normalized[] = [
                'id_component' => $idComponent,
                'id_product' => (int) $matchedSelection['id_product'],
                'id_product_attribute' => (int) $matchedSelection['id_product_attribute'],
                'quantity' => $quantity,
                'customization' => $customization,
            ];
        }

        if ($errors) {
            throw new \RuntimeException(implode(' ', array_values(array_unique($errors))));
        }

        return new PackConfiguration($configuration->getIdProduct(), $normalized, $configuration->getQuantity());
    }
}
