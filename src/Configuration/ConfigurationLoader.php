<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

use Infocyph\ArrayKit\ArrayKit;

/** @internal */
final class ConfigurationLoader
{
    /**
     * @param list<array<array-key, mixed>> $layers
     * @param list<string> $files
     */
    public function load(array $layers = [], array $files = []): Configuration
    {
        $config = ArrayKit::config();
        foreach ($layers as $layer) {
            $config->merge($layer);
        }
        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new \InvalidArgumentException(sprintf('Configuration file "%s" does not exist.', $file));
            }
            $data = require $file;
            if (!is_array($data)) {
                throw new \UnexpectedValueException(sprintf('Configuration file "%s" must return an array.', $file));
            }
            $config->merge($data);
        }

        return new Configuration($config);
    }
}
