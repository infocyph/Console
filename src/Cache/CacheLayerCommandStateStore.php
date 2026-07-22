<?php

declare(strict_types=1);

namespace Infocyph\Console\Cache;

use Infocyph\CacheLayer\Cache\CacheInterface;

final readonly class CacheLayerCommandStateStore implements CommandStateStore
{
    public function __construct(private CacheInterface $cache, private string $prefix = 'console:') {}

    public function forget(string $key): void
    {
        $this->cache->delete($this->prefix . $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->prefix . $key, $default);
    }

    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): void
    {
        $this->cache->set($this->prefix . $key, $value, $ttl);
    }
}
