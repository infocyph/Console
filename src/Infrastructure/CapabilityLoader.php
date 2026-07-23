<?php

declare(strict_types=1);

namespace Infocyph\Console\Infrastructure;

use Closure;
use Infocyph\Console\Command\Capability;
use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Identity\ExecutionIdGenerator;
use Infocyph\Console\Identity\UidExecutionIdGenerator;
use Infocyph\InterMix\DI\Container;

/** @internal */
final class CapabilityLoader
{
    /** @var \WeakMap<Container, array<string, true>> */
    private \WeakMap $loaded;

    /** @param array<string, list<Closure(Container): void>> $configurers */
    public function __construct(private array $configurers = [], private readonly ?ExecutionIdGenerator $ids = null)
    {
        $this->loaded = new \WeakMap();
    }

    public function load(Container $container, CommandDescriptor $command): ?CommandExecution
    {
        foreach ($command->capabilities() as $capability) {
            $loaded = $this->loaded[$container] ?? [];
            if (isset($loaded[$capability->value])) {
                continue;
            }

            foreach ($this->configurers[$capability->value] ?? [] as $configurer) {
                $configurer($container);
            }

            $loaded[$capability->value] = true;
            $this->loaded[$container] = $loaded;
        }

        if (!in_array(Capability::IDENTITY, $command->capabilities(), true)) {
            return null;
        }

        $generator = $this->ids ?? new UidExecutionIdGenerator();
        $container->definitions()->bind(ExecutionIdGenerator::class, $generator);

        return new CommandExecution($generator->generate(), $command->name(), time());
    }
}
