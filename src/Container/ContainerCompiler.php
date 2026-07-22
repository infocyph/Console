<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

final readonly class ContainerCompiler
{
    public function __construct(private ContainerFactory $factory) {}

    public function compile(ContainerConfigurator $configuration, string $path): void
    {
        $this->factory->create($configuration)->compileTo($path);
    }
}
