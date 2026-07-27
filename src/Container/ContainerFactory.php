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
        $provider = $configuration->containerProvider();
        if ($provider === null && $this->standalone !== null) {
            return $this->standalone;
        }

        $container = $provider?->container() ?? new Container('infocyph.console.' . bin2hex(random_bytes(8)));

        if ($provider !== null && isset($this->configured[$container])) {
            return $container;
        }

        if ($provider === null) {
            $container->options()->setOptions(injection: true);
            $container->enableLazyLoading();
        }

        foreach ($configuration->providers() as $provider) {
            $container->registration()->import($provider);
        }

        foreach ($configuration->configurers() as $configurer) {
            $configurer($container);
        }

        $compiledContainer = $configuration->compiledContainerPath();
        if ($compiledContainer !== null && is_file($compiledContainer)) {
            $container->useCompiled($compiledContainer);
        }

        if ($provider !== null) {
            $this->configured[$container] = true;
        } else {
            $this->standalone = $container;
        }

        return $container;
    }
}
