<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\ReqShield\CompiledValidator;
use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Validator;

/** @internal */
final class ValidationCompiler
{
    /** @var array<string, CompiledValidation|null> */
    private array $cache = [];

    public function __construct(private readonly ?DatabaseProvider $database = null) {}

    /** @param array<string,array{rules:list<string>,sanitizers:list<string>}> $manifest */
    public function compile(CommandDescriptor $command, array $manifest = []): ?CompiledValidation
    {
        if ($manifest !== []) {
            return $this->build($command, $manifest);
        }

        return $this->cache[$command->class()] ??= $this->build($command);
    }

    /** @param array<string,array{rules:list<string>,sanitizers:list<string>}> $manifest */
    private function build(CommandDescriptor $command, array $manifest = []): ?CompiledValidation
    {
        $rules = [];
        $sanitizers = [];
        $fields = [];
        foreach ([...$command->arguments(), ...$command->options()] as $definition) {
            $compiled = $manifest[$definition->name()] ?? null;
            $fieldRules = $compiled['rules'] ?? $definition->ruleset();
            $fieldSanitizers = $compiled['sanitizers'] ?? $definition->sanitizers();
            if ($fieldRules === [] && $fieldSanitizers === []) {
                continue;
            }
            $name = $definition->name();
            if ($fieldRules === []) {
                $fieldRules = ['nullable'];
            }
            if ($this->optional($definition) && !in_array('required', $fieldRules, true) && !in_array('nullable', $fieldRules, true)) {
                array_unshift($fieldRules, 'nullable');
            }
            $rules[$name] = $fieldRules;
            if ($fieldSanitizers !== []) {
                $sanitizers[$name] = $fieldSanitizers;
            }
            $fields[$name] = true;
        }
        if ($rules === []) {
            return null;
        }
        $validator = Validator::compile($rules, $this->database)->validator();
        if ($sanitizers !== []) {
            $validator->setSanitizers($sanitizers);
        }

        return new CompiledValidation(new CompiledValidator($validator), $fields);
    }

    private function optional(Argument|Option $definition): bool
    {
        return $definition instanceof Option || !$definition->isRequired();
    }
}
