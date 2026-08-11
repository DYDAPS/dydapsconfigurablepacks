<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
    private const ALLOWED_COMPONENT_KEYS = ['id_component', 'id_product', 'id_product_attribute', 'quantity'];

    /**
     * Convert decoded request data into a normalized configuration.
     *
     * The request shape is rejected before normalization so untrusted fields
     * cannot silently disappear before the business validator runs.
     *
     * @param array{
     *     components?: list<array<string, mixed>>
     * } $payload Decoded JSON request payload.
     * @param int $idProduct Native PrestaShop product sold as the pack.
     * @param int $packQuantity Number of configured packs requested.
     *
     * @return PackConfiguration
     *
     * @throws \RuntimeException When the request shape contains unexpected or invalid fields.
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

        if (!isset($payload['components']) || !is_array($payload['components'])) {
            throw new \RuntimeException('Invalid pack components payload.');
        }

        $components = [];
        foreach ($payload['components'] as $component) {
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
            ];
        }

        return new PackConfiguration($idProduct, $components, $packQuantity);
    }
}
