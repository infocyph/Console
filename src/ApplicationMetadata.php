<?php

declare(strict_types=1);

namespace Infocyph\Console;

/**
 * @internal
 */
final readonly class ApplicationMetadata
{
    public function __construct(private string $name, private string $version)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('An application name is required.');
        }
        if ($version === '') {
            throw new \InvalidArgumentException('An application version is required.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }
}
