<?php

declare(strict_types=1);

namespace Infocyph\Console\Cache;

interface CommandStateStore
{
    public function forget(string $key): void;

    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, int|\DateInterval|null $ttl = null): void;
}
