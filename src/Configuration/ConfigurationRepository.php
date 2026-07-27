<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

use Infocyph\Console\Exception\UsageException;

/** @internal */
final class ConfigurationRepository implements ConfigurationProvider
{
    private ?Configuration $configuration = null;

    private ?string $profile = null;

    /**
     * @param list<array<array-key, mixed>> $layers
     * @param list<string> $files
     * @param array<string, mixed> $rules
     * @param array<string, array<int, (callable(): mixed)|string>|(callable(): mixed)|string> $sanitizers
     * @param array<string, array<array-key, mixed>> $profiles
     */
    public function __construct(
        private readonly ConfigurationLoader $loader,
        private readonly array $layers,
        private readonly array $files,
        private readonly array $rules = [],
        private readonly array $sanitizers = [],
        private readonly bool $strict = false,
        private readonly array $profiles = [],
    ) {}

    public function configuration(): Configuration
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }
        $layers = $this->layers;
        if ($this->profile !== null) {
            $layers[] = $this->profiles[$this->profile];
        }
        $configuration = $this->loader->load($layers, $this->files);

        return $this->configuration = new ConfigurationValidator()->validate($configuration, $this->rules, $this->sanitizers, $this->strict);
    }

    public function useProfile(?string $profile): void
    {
        if ($profile !== null && !array_key_exists($profile, $this->profiles)) {
            throw new UsageException(sprintf('Configuration profile "%s" is not defined.', $profile));
        }
        if ($this->profile !== $profile) {
            $this->profile = $profile;
            $this->configuration = null;
        }
    }
}
