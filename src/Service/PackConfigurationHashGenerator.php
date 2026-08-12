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
     * @param PackConfiguration $configuration configuration to hash
     *
     * @return string SHA-256 hash of the normalized configuration payload
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
