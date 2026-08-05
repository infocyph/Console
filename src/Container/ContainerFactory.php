<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

use Infocyph\InterMix\DI\Container;

/**
 * @internal
 */
final class ContainerFactory
{
    /** @var \WeakMap<Container, true> */
    private \WeakMap $configured;

    private ?Container $standalone = null;

    public function __construct()
    {
        $this->configured = new \WeakMap();
    }

    public function create(ContainerConfigurator $configuration): Container
    {
        $containerProvider = $configuration->containerProvider();
        if ($containerProvider === null && $this->standalone !== null) {
            return $this->standalone;
        }

        $container = $containerProvider?->container() ?? new Container('infocyph.console.' . bin2hex(random_bytes(8)));

        if ($containerProvider !== null && isset($this->configured[$container])) {
            return $container;
        }

        $this->configure($container, $configuration);

        if ($containerProvider !== null) {
            $this->configured[$container] = true;
        } else {
            $this->standalone = $container;
        }

        return $container;
    }

    private function configure(Container $container, ContainerConfigurator $configuration): void
    {
        foreach ($configuration->providers() as $serviceProvider) {
            $container->registration()->import($serviceProvider);
        }

        foreach ($configuration->configurers() as $configurer) {
            $configurer($container);
        }

        $this->loadCompiledContainer($container, $configuration);
    }

    private function loadCompiledContainer(Container $container, ContainerConfigurator $configuration): void
    {
        $compiledContainer = $configuration->compiledContainerPath();
        if ($compiledContainer !== null && is_file($compiledContainer)) {
            $fingerprint = $configuration->compiledContainerFingerprint();
            if ($fingerprint === null) {
                $container->useCompiled($compiledContainer);
            } else {
                $container->usePrevalidated($compiledContainer, $fingerprint);
            }
        }
    }
}
