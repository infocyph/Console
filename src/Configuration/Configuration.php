<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Config\Config;

final readonly class Configuration
{
    /** @internal */
    public function __construct(private Config $config) {}

    /** @param array<array-key, mixed> $configuration */
    public static function fromArray(array $configuration): self
    {
        return new self(ArrayKit::config($configuration));
    }

    public static function fromConfig(Config $configuration): self
    {
        return new self($configuration);
    }

    /** @return array<array-key, mixed> */
    public function all(): array
    {
        return $this->config->get() ?? [];
    }

    /** @return array<array-key, mixed>|null */
    public function array(string $key, ?array $default = null): ?array
    {
        return $this->config->getArray($key, $default);
    }

    public function bool(string $key, ?bool $default = null): ?bool
    {
        return $this->config->getBool($key, $default);
    }

    public function get(string|array|null $key = null, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function int(string $key, ?int $default = null): ?int
    {
        return $this->config->getInt($key, $default);
    }

    public function string(string $key, ?string $default = null): ?string
    {
        return $this->config->getString($key, $default);
    }
}
