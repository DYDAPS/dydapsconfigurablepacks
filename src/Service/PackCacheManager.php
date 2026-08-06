<?php
declare(strict_types=1);

namespace Dydaps\ConfigurablePacks\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Small request-local cache for module services.
 */
final class PackCacheManager
{
    /** @var array<string,mixed> */
    private array $cache = [];

    /**
     * Return a cached value or null when no entry exists.
     *
     * @param string $key Cache key.
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * Store a value for the current request lifecycle.
     *
     * @param string $key Cache key.
     * @param mixed $value
     *
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->cache[$key] = $value;
    }

    /**
     * Clear all cached values, or only values whose keys start with a prefix.
     *
     * @param string|null $prefix Optional cache-key prefix.
     *
     * @return void
     */
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
