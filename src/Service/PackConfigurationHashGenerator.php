<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Generates stable hashes for configured pack selections.
 *
 * The hash identifies one exact configuration inside a cart, allowing the same
 * pack product to appear multiple times when customers choose different
 * component products or combinations.
 */
final class PackConfigurationHashGenerator
{
    /**
     * Generate a deterministic SHA-256 hash for the configuration.
     *
     * @param PackConfiguration $configuration Configuration to hash.
     *
     * @return string SHA-256 hash of the normalized configuration payload.
     */
    public function generate(PackConfiguration $configuration): string
    {
        $data = $configuration->toArray();
        $data['components'] = $this->normalizeComponents($data['components']);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Normalize component order and keys before hashing.
     *
     * @param list<array<string, int>> $components
     *
     * @return list<array<string, int>>
     */
    private function normalizeComponents(array $components): array
    {
        foreach ($components as &$component) {
            ksort($component);
        }
        unset($component);

        usort($components, static function (array $left, array $right): int {
            return ($left['id_component'] ?? 0) <=> ($right['id_component'] ?? 0);
        });

        return $components;
    }
}
