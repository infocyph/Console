<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

/**
 * @internal
 */
final class CommandResolverProvider
{
    private ?CommandResolver $resolver = null;

    /**
     * @param \Closure $factory Lazy command-resolver factory.
     * @phpstan-param \Closure(): CommandResolver $factory
     * @psalm-param \Closure(): CommandResolver $factory
     */
    public function __construct(private readonly \Closure $factory) {}

    public function get(): CommandResolver
    {
        return $this->resolver ??= ($this->factory)();
    }
}
