<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

use Closure;
use Infocyph\InterMix\DI\Container;
use Infocyph\InterMix\DI\Support\ServiceProviderInterface;

/**
 * @internal
 */
final class ContainerConfigurator
{
    private ?string $compiledContainer = null;

    private ?string $compiledContainerFingerprint = null;

    /** @var list<Closure(Container): void> */
    private array $configurers = [];

    private ?ContainerProvider $provider = null;

    /** @var list<class-string<ServiceProviderInterface>|ServiceProviderInterface> */
    private array $providers = [];

    public function compiledContainer(?string $path, ?string $fingerprint = null): void
    {
        if ($fingerprint !== null && preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
            throw new \InvalidArgumentException(
                'A prevalidated container fingerprint must be a SHA-256 hexadecimal digest.',
            );
        }
        if ($path === null && $fingerprint !== null) {
            throw new \InvalidArgumentException('A prevalidated container fingerprint requires an artifact path.');
        }

        $this->compiledContainer = $path;
        $this->compiledContainerFingerprint = $fingerprint;
    }

    public function compiledContainerFingerprint(): ?string
    {
        return $this->compiledContainerFingerprint;
    }

    public function compiledContainerPath(): ?string
    {
        return $this->compiledContainer;
    }

    /**
     * @param Closure $configurer Container configuration callback.
     * @phpstan-param Closure(Container): void $configurer
     * @psalm-param Closure(Container): void $configurer
     */
    public function configure(Closure $configurer): void
    {
        $this->configurers[] = $configurer;
    }

    /** @return list<Closure(Container): void> */
    public function configurers(): array
    {
        return $this->configurers;
    }

    public function containerProvider(): ?ContainerProvider
    {
        return $this->provider;
    }

    /** @param class-string<ServiceProviderInterface>|ServiceProviderInterface $provider */
    public function provider(string|ServiceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return list<class-string<ServiceProviderInterface>|ServiceProviderInterface> */
    public function providers(): array
    {
        return $this->providers;
    }

    public function useContainerProvider(ContainerProvider $provider): void
    {
        $this->provider = $provider;
    }
}
