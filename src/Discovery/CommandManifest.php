<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandRegistry;
use Infocyph\Console\Support\ManifestValue;

final class CommandManifest
{
    /** @return array<string,mixed> */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Command manifest "%s" does not exist.', $path));
        }

        return ManifestValue::map(require $path, 'commands');
    }

    public static function registry(string $path): CommandRegistry
    {
        $manifest = self::load($path);

        if (($manifest['version'] ?? null) !== 2) {
            throw new \UnexpectedValueException(sprintf(
                'Command manifest "%s" must use format version 2.',
                $path,
            ));
        }

        return CommandRegistry::fromIndexedManifest($manifest, dirname($path));
    }
}
