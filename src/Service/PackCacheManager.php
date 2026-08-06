<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PackCacheManager
{
    /** @var array<string,mixed> */
    private array $cache = [];

    public function get(string $key)
    {
        return $this->cache[$key] ?? null;
    }

    public function set(string $key, $value): void
    {
        $this->cache[$key] = $value;
    }

    public function clear(?string $prefix = null): void
    {
        if ($prefix === null) {
            $this->cache = [];
            return;
        }

        foreach (array_keys($this->cache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->cache[$key]);
            }
        }
    }
}
