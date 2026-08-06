<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

use Dydaps\ConfigurablePacks\Model\PackConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackConfigurationHashGenerator
{
    public function generate(PackConfiguration $configuration): string
    {
        $data = $configuration->toArray();
        $data['components'] = $this->normalizeComponents($data['components']);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<int,array<string,int>> $components
     *
     * @return array<int,array<string,int>>
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
