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
     * Convert decoded request data into a normalized configuration.
     *
     * Invalid component entries are ignored and quantities are normalized to at
     * least one so downstream validators can focus on business constraints.
     *
     * @param array{
     *     components?: list<array<string, mixed>>
     * } $payload Decoded JSON request payload.
     * @param int $idProduct Native PrestaShop product sold as the pack.
     * @param int $packQuantity Number of configured packs requested.
     *
     * @return PackConfiguration
     */
    public function fromRequest(array $payload, int $idProduct, int $packQuantity = 1): PackConfiguration
    {
        $components = [];
        foreach ((array) ($payload['components'] ?? []) as $component) {
            if (!is_array($component)) {
                continue;
            }
            $components[] = [
                'id_component' => (int) ($component['id_component'] ?? 0),
                'id_product' => (int) ($component['id_product'] ?? 0),
                'id_product_attribute' => (int) ($component['id_product_attribute'] ?? 0),
                'quantity' => max(1, (int) ($component['quantity'] ?? 1)),
            ];
        }

        return new PackConfiguration($idProduct, $components, $packQuantity);
    }
}
