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

use Dydaps\ConfigurablePacks\Model\PackConfiguration;

/**
 * Builds normalized pack configuration objects from front-office requests.
 */
final class PackConfigurationService
{
    /**
     * Front-office payload keys accepted by the module.
     *
     * @var array<int, string>
     */
    private const ALLOWED_PAYLOAD_KEYS = ['components'];

    /**
     * Component payload keys accepted by the module.
     *
     * @var array<int, string>
     */
    private const ALLOWED_COMPONENT_KEYS = ['id_component', 'id_product', 'id_product_attribute', 'quantity', 'customization', 'customization_fields'];

    /**
     * Convert decoded request data into a normalized configuration.
     *
     * The request shape is rejected before normalization so untrusted fields
     * cannot silently disappear before the business validator runs.
     *
     * @param array{
     *     components?: list<array<string, mixed>>
     * } $payload Decoded JSON request payload
     * @param int $idProduct native PrestaShop product sold as the pack
     * @param int $packQuantity number of configured packs requested
     *
     * @return PackConfiguration
     *
     * @throws \RuntimeException when the request shape contains unexpected or invalid fields
     */
    public function fromRequest(array $payload, int $idProduct, int $packQuantity = 1): PackConfiguration
    {
        if ($idProduct <= 0) {
            throw new \RuntimeException('Invalid pack product.');
        }
        if ($packQuantity <= 0) {
            throw new \RuntimeException('Invalid pack quantity.');
        }

        $unexpectedPayloadKeys = array_diff(array_keys($payload), self::ALLOWED_PAYLOAD_KEYS);
        if ($unexpectedPayloadKeys) {
            throw new \RuntimeException('Unexpected pack configuration payload field.');
        }

        $rawComponents = $payload['components'] ?? null;
        if (!is_array($rawComponents)) {
            throw new \RuntimeException('Invalid pack components payload.');
        }

        $components = [];
        foreach ($rawComponents as $component) {
            if (!is_array($component)) {
                throw new \RuntimeException('Invalid pack component payload.');
            }

            $unexpectedComponentKeys = array_diff(array_keys($component), self::ALLOWED_COMPONENT_KEYS);
            if ($unexpectedComponentKeys) {
                throw new \RuntimeException('Unexpected pack component payload field.');
            }

            $quantity = (int) ($component['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new \RuntimeException('Invalid pack component quantity.');
            }

            $components[] = [
                'id_component' => (int) ($component['id_component'] ?? 0),
                'id_product' => (int) ($component['id_product'] ?? 0),
                'id_product_attribute' => (int) ($component['id_product_attribute'] ?? 0),
                'quantity' => $quantity,
                'customization' => $this->normalizeCustomization($component['customization'] ?? null),
                'customization_fields' => $this->normalizeCustomizationFields($component['customization_fields'] ?? null),
            ];
        }

        return new PackConfiguration($idProduct, $components, $packQuantity);
    }

    /**
     * Normalize a customer customization text.
     *
     * @param mixed $customization submitted customization value
     *
     * @return string sanitized text, truncated to the module storage limit
     */
    private function normalizeCustomization($customization): string
    {
        $text = trim((string) $customization);
        if ($text === '') {
            return '';
        }

        return mb_substr(strip_tags($text), 0, 255);
    }

    /**
     * Normalize native customization field values submitted per component.
     *
     * Each entry must reference a positive customization field identifier. The
     * resulting list is sorted by field id so configuration hashes stay stable
     * regardless of the client-side serialization order.
     *
     * @param mixed $customizationFields submitted field values
     *
     * @return list<array{
     *     id_customization_field: int,
     *     value: string
     * }>
     */
    private function normalizeCustomizationFields($customizationFields): array
    {
        if (!is_array($customizationFields)) {
            return [];
        }

        $fields = [];
        foreach ($customizationFields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $idField = (int) ($field['id_customization_field'] ?? 0);
            if ($idField <= 0) {
                continue;
            }

            $fields[] = [
                'id_customization_field' => $idField,
                'value' => $this->normalizeCustomization($field['value'] ?? null),
            ];
        }

        usort($fields, static fn (array $left, array $right): int => $left['id_customization_field'] <=> $right['id_customization_field']);

        return $fields;
    }
}
