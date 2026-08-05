<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

final readonly class ContainerCompiler
{
    public function __construct(private ContainerFactory $factory) {}

    /**
     * @return array{
     *     path: string,
     *     fingerprint: string,
     *     compiled: array<int, string>,
     *     skipped: array<string, string>
     * }
     */
    public function compile(ContainerConfigurator $configuration, string $path): array
    {
        $container = $this->factory->create($configuration);
        $container->compileTo($path);

        return $container->compilationReport()
            ?? throw new \LogicException('InterMix did not publish a container compilation report.');
    }
}
