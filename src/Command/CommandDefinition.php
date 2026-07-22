<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;

final class CommandDefinition
{
    /** @var list<string> */
    private array $aliases = [];

    /** @var array<string, Argument> */
    private array $arguments = [];

    /** @var list<Capability> */
    private array $capabilities = [];

    private string $description = '';

    private bool $hidden = false;

    private ?string $name = null;

    /** @var array<string, Option> */
    private array $options = [];

    private bool $requiresOtp = false;

    public function alias(string $alias): self
    {
        if (preg_match('/^[a-z][a-z0-9]*(?::[a-z][a-z0-9-]*)*$/', $alias) !== 1 || $alias === $this->name) {
            throw new \InvalidArgumentException('A command alias must be distinct and use lowercase colon-separated segments.');
        }

        if (!in_array($alias, $this->aliases, true)) {
            $this->aliases[] = $alias;
        }

        return $this;
    }

    public function argument(Argument $argument): self
    {
        if (isset($this->options[$argument->name()])) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" conflicts with an option name.', $argument->name()));
        }
        if (isset($this->arguments[$argument->name()])) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" is already defined.', $argument->name()));
        }
        if ($this->arguments !== []) {
            $last = end($this->arguments);
            if ($last->isVariadic()) {
                throw new \InvalidArgumentException('No arguments may follow a variadic argument.');
            }
            if ($argument->isRequired() && !$last->isRequired()) {
                throw new \InvalidArgumentException('Required arguments cannot follow optional arguments.');
            }
        }

        if ($argument->isVariadic() && $this->arguments !== []) {
            $last = end($this->arguments);
            if ($last->isVariadic()) {
                throw new \InvalidArgumentException('Only one variadic argument may be defined.');
            }
        }

        $this->arguments[$argument->name()] = $argument;

        return $this;
    }

    /** @param list<Capability> $capabilities */
    public function capabilities(array $capabilities): self
    {
        $this->capabilities = [];
        foreach ($capabilities as $capability) {
            if (!in_array($capability, $this->capabilities, true)) {
                $this->capabilities[] = $capability;
            }
        }

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param class-string<CommandContract> $class
     *
     * @internal
     */
    public function descriptor(string $class): CommandDescriptor
    {
        if ($this->name === null) {
            throw new \LogicException(sprintf('%s did not define a command name.', $class));
        }
        if (in_array($this->name, $this->aliases, true)) {
            throw new \LogicException('A command alias cannot equal its canonical name.');
        }
        if ($this->requiresOtp && !in_array(Capability::OTP, $this->capabilities, true)) {
            $this->capabilities[] = Capability::OTP;
        }

        $arguments = array_values($this->arguments);
        foreach ($arguments as $index => $argument) {
            if ($argument->isVariadic() && $index !== array_key_last($arguments)) {
                throw new \LogicException('A variadic argument must be the final command argument.');
            }
        }

        return new CommandDescriptor(
            $class,
            $this->name,
            $this->description,
            $arguments,
            array_values($this->options),
            $this->aliases,
            $this->hidden,
            $this->capabilities,
            $this->requiresOtp,
        );
    }

    public function hidden(bool $hidden = true): self
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function name(string $name): self
    {
        if (preg_match('/^[a-z][a-z0-9]*(?::[a-z][a-z0-9-]*)*$/', $name) !== 1) {
            throw new \InvalidArgumentException('A command name must use lowercase colon-separated segments.');
        }

        $this->name = $name;

        return $this;
    }

    public function option(Option $option): self
    {
        if (isset($this->arguments[$option->name()])) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" conflicts with an argument name.', $option->name()));
        }
        if (isset($this->options[$option->name()])) {
            throw new \InvalidArgumentException(sprintf('Option "--%s" is already defined.', $option->name()));
        }

        foreach ($this->options as $defined) {
            if ($option->shortName() !== null && $option->shortName() === $defined->shortName()) {
                throw new \InvalidArgumentException(sprintf('Option shortcut "-%s" is already defined.', $option->shortName()));
            }
        }

        $this->options[$option->name()] = $option;

        return $this;
    }

    public function requiresOtp(bool $required = true): self
    {
        $this->requiresOtp = $required;
        if ($required && !in_array(Capability::OTP, $this->capabilities, true)) {
            $this->capabilities[] = Capability::OTP;
        }

        return $this;
    }
}
