<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

use Infocyph\InterMix\DI\Container;

/**
 * @internal
 */
final class ContainerFactory
{
    public function create(ContainerConfigurator $configuration): Container
    {
        $container = new Container('infocyph.console.' . bin2hex(random_bytes(8)));
        $container->options()->setOptions(injection: true);
        $container->enableLazyLoading();

        foreach ($configuration->providers() as $provider) {
            $container->registration()->import($provider);
        }

        foreach ($configuration->configurers() as $configurer) {
            $configurer($container);
        }

        new CompiledContainerLoader()->load($container, $configuration->compiledContainerPath());

        return $container;
    }
}
