<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

use Infocyph\Console\Command\CommandContext;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Input\ArgumentCollection;
use Infocyph\Console\Input\OptionCollection;
use Infocyph\Console\Input\ParsedInput;
use Infocyph\Console\IO\IO;
use Infocyph\InterMix\DI\Container;
use Infocyph\InterMix\DI\Support\LifetimeEnum;

/**
 * @internal
 */
final class CommandScope
{
    private bool $active = false;

    public function __construct(private readonly Container $container, private readonly string $name) {}

    public function enter(CommandContext $context): void
    {
        $this->container->enterScope($this->name);
        $this->active = true;

        $definitions = $this->container->definitions();
        $definitions->bind(CommandContext::class, $context, LifetimeEnum::Scoped);
        $definitions->bind(ParsedInput::class, $context->input(), LifetimeEnum::Scoped);
        $definitions->bind(ArgumentCollection::class, $context->arguments(), LifetimeEnum::Scoped);
        $definitions->bind(OptionCollection::class, $context->options(), LifetimeEnum::Scoped);
        $definitions->bind(IO::class, $context->io(), LifetimeEnum::Scoped);
        if ($context->execution() !== null) {
            $definitions->bind(CommandExecution::class, $context->execution(), LifetimeEnum::Scoped);
        }
    }

    public function leave(): void
    {
        if (!$this->active) {
            return;
        }

        $this->container->leaveScope();
        $this->active = false;
    }
}
