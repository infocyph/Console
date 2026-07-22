<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

use Infocyph\InterMix\DI\Container;

/**
 * @internal
 */
final class CompiledContainerLoader
{
    public function load(Container $container, ?string $path): void
    {
        if ($path !== null && is_file($path)) {
            $container->useCompiled($path);
        }
    }
}
