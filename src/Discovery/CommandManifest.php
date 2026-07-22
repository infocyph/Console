<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandRegistry;

final class CommandManifest
{
    /** @return array<string,mixed> */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Command manifest "%s" does not exist.', $path));
        }
        $manifest = require $path;
        if (!is_array($manifest)) {
            throw new \UnexpectedValueException(sprintf('Command manifest "%s" must return an array.', $path));
        }

        return $manifest;
    }

    public static function registry(string $path): CommandRegistry
    {
        $manifest = self::load($path);

        if (isset($manifest['version'], $manifest['commands']) && is_int($manifest['version']) && $manifest['version'] === 2) {
            return CommandRegistry::fromIndexedManifest($manifest, dirname($path));
        }

        return CommandRegistry::fromManifest($manifest);
    }
}
