<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

use Infocyph\Console\Support\PhpManifestWriter;

final class ConfigurationCompiler
{
    public function compile(Configuration $configuration, string $path): void
    {
        PhpManifestWriter::write($configuration->all(), $path, 'compiled configuration');
    }
}
