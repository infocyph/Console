<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Exception\UsageException;

/**
 * @internal
 */
final class ArgvParser
{
    /** @param list<string> $tokens */
    public function parse(CommandDescriptor $command, array $tokens): ParsedInput
    {
        $longOptions = [];
        $shortOptions = [];
        $values = $this->optionDefaults($command->options());

        foreach ($command->options() as $option) {
            $longOptions[$option->name()] = $option;
            if ($option->shortName() !== null) {
                $shortOptions[$option->shortName()] = $option;
            }
        }

        $seen = [];
        $positionals = [];
        $endOfOptions = false;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($endOfOptions) {
                $positionals[] = $token;

                continue;
            }

            if ($token === '--') {
                $endOfOptions = true;

                continue;
            }

            if (str_starts_with($token, '--') && $token !== '--') {
                $this->parseLongOption($token, $tokens, $index, $longOptions, $seen, $values);

                continue;
            }

            if (str_starts_with($token, '-') && $token !== '-') {
                $this->parseShortOptions($token, $tokens, $index, $shortOptions, $seen, $values);

                continue;
            }

            $positionals[] = $token;
        }

        return new ParsedInput(
            new ArgumentCollection($this->parseArguments($command->arguments(), $positionals)),
            new OptionCollection($values),
            $tokens,
        );
    }

    private function boolean(string $value, string $name): bool
    {
        return match (strtolower($value)) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new UsageException(sprintf('Value for "%s" must be a boolean.', $name)),
        };
    }

    private function convert(string $value, ValueType $type, string $name): mixed
    {
        return match ($type) {
            ValueType::STRING => $value,
            ValueType::INTEGER => $this->integer($value, $name),
            ValueType::FLOAT => $this->float($value, $name),
            ValueType::BOOLEAN => $this->boolean($value, $name),
        };
    }

    private function defaultValue(Argument|Option $definition): mixed
    {
        $value = $definition->defaultValue();
        $environment = $definition->environmentVariable();
        if ($environment !== null) {
            $fromEnvironment = getenv($environment);
            if ($fromEnvironment !== false) {
                $value = $fromEnvironment;
            }
        }

        if ($value === null) {
            return $value;
        }

        if ($definition instanceof Option && !$definition->acceptsValue()) {
            return is_string($value) ? $this->boolean($value, $definition->name()) : (bool) $value;
        }

        if ($definition instanceof Option && $definition->multipleValues()) {
            return is_string($value)
                ? [$this->convert($value, $definition->valueType(), $definition->name())]
                : $value;
        }

        if ($definition instanceof Argument && $definition->isVariadic()) {
            if (!is_array($value)) {
                $delimiter = $definition->environmentDelimiter();
                if ($delimiter === null) {
                    throw new \LogicException('Variadic argument values require an explicit environment delimiter.');
                }
                $value = $delimiter === '' ? [$value] : explode($delimiter, (string) $value);
            }

            return array_map(
                fn(mixed $item): mixed => is_string($item)
                    ? $this->convert($item, $definition->valueType(), $definition->name())
                    : $item,
                $value,
            );
        }

        return is_string($value)
            ? $this->convert($value, $definition->valueType(), $definition->name())
            : $value;
    }

    private function float(string $value, string $name): float
    {
        if (!is_numeric($value)) {
            throw new UsageException(sprintf('Value for "%s" must be a number.', $name));
        }

        return (float) $value;
    }

    private function integer(string $value, string $name): int
    {
        if (preg_match('/^[+-]?\\d+$/D', $value) !== 1) {
            throw new UsageException(sprintf('Value for "%s" must be an integer.', $name));
        }

        return (int) $value;
    }

    /**
     * @param list<Option> $options
     * @return array<string,mixed>
     */
    private function optionDefaults(array $options): array
    {
        $values = [];
        foreach ($options as $option) {
            $values[$option->name()] = $this->defaultValue($option);
        }

        return $values;
    }

    /**
     * @param list<Argument> $definitions
     * @param list<string> $positionals
     * @return array<string, mixed>
     */
    private function parseArguments(array $definitions, array $positionals): array
    {
        $values = [];
        $position = 0;

        foreach ($definitions as $definition) {
            if ($definition->isVariadic()) {
                $remaining = array_slice($positionals, $position);
                if ($remaining !== []) {
                    $values[$definition->name()] = array_map(
                        fn(string $value): mixed => $this->convert($value, $definition->valueType(), $definition->name()),
                        $remaining,
                    );
                    $position = count($positionals);

                    continue;
                }

                $fallback = $this->defaultValue($definition);
                if ($definition->isRequired() && $fallback === []) {
                    throw new UsageException(sprintf('Argument "%s" is required.', $definition->name()));
                }
                $values[$definition->name()] = $fallback;
                $position = count($positionals);

                continue;
            }

            if (array_key_exists($position, $positionals)) {
                $values[$definition->name()] = $this->convert($positionals[$position], $definition->valueType(), $definition->name());
                $position++;

                continue;
            }

            $fallback = $this->defaultValue($definition);
            if ($definition->isRequired() && $fallback === null) {
                throw new UsageException(sprintf('Argument "%s" is required.', $definition->name()));
            }
            $values[$definition->name()] = $fallback;
        }

        if (array_key_exists($position, $positionals)) {
            throw new UsageException(sprintf('Unexpected argument "%s".', $positionals[$position]));
        }

        return $values;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, Option> $options
     * @param array<string, true> $seen
     * @param array<string, mixed> $values
     */
    private function parseLongOption(string $token, array $tokens, int &$index, array $options, array &$seen, array &$values): void
    {
        [$name, $inlineValue] = array_pad(explode('=', substr($token, 2), 2), 2, null);
        $negated = str_starts_with((string) $name, 'no-');
        $optionName = $negated ? substr((string) $name, 3) : $name;
        $option = $options[$optionName] ?? null;

        if ($option === null) {
            throw new UsageException($this->unknownOption('--' . $name, array_map(static fn(string $candidate): string => '--' . $candidate, array_keys($options))));
        }

        if ($negated) {
            if (!$option->isNegatable() || $inlineValue !== null) {
                throw new UsageException(sprintf('Option "--%s" is not defined.', $name));
            }
            [$seen, $values] = $this->storeOption($option, false, $seen, $values);

            return;
        }

        if (!$option->acceptsValue()) {
            if ($inlineValue !== null) {
                throw new UsageException(sprintf('Option "--%s" does not accept a value.', $name));
            }
            [$seen, $values] = $this->storeOption($option, true, $seen, $values);

            return;
        }

        if ($inlineValue === null) {
            $index++;
            if (!array_key_exists($index, $tokens)) {
                throw new UsageException(sprintf('Option "--%s" requires a value.', $name));
            }
            $inlineValue = $tokens[$index];
        }

        [$seen, $values] = $this->storeOption($option, $this->convert($inlineValue, $option->valueType(), '--' . $name), $seen, $values);
    }

    /**
     * @param list<string> $tokens
     * @param array<string, Option> $options
     * @param array<string, true> $seen
     * @param array<string, mixed> $values
     */
    private function parseShortOptions(string $token, array $tokens, int &$index, array $options, array &$seen, array &$values): void
    {
        $shortcuts = substr($token, 1);
        for ($offset = 0; $offset < strlen($shortcuts); $offset++) {
            $shortcut = $shortcuts[$offset];
            $option = $options[$shortcut] ?? null;
            if ($option === null) {
                throw new UsageException($this->unknownOption('-' . $shortcut, array_map(static fn(string $candidate): string => '-' . $candidate, array_keys($options))));
            }

            if (!$option->acceptsValue()) {
                [$seen, $values] = $this->storeOption($option, true, $seen, $values);

                continue;
            }

            $value = substr($shortcuts, $offset + 1);
            if ($value === '') {
                $index++;
                if (!array_key_exists($index, $tokens)) {
                    throw new UsageException(sprintf('Option "-%s" requires a value.', $shortcut));
                }
                $value = $tokens[$index];
            }

            [$seen, $values] = $this->storeOption($option, $this->convert($value, $option->valueType(), '-' . $shortcut), $seen, $values);

            return;
        }
    }

    /** @param array<string, true> $seen @param array<string, mixed> $values @return array{array<string,true>,array<string,mixed>} */
    private function storeOption(Option $option, mixed $value, array $seen, array $values): array
    {
        $name = $option->name();
        if (isset($seen[$name]) && !$option->multipleValues()) {
            throw new UsageException(sprintf('Option "--%s" may not be used more than once.', $name));
        }

        $seen[$name] = true;
        if ($option->multipleValues()) {
            $values[$name][] = $value;

            return [$seen, $values];
        }

        $values[$name] = $value;

        return [$seen, $values];
    }

    /** @param list<string> $candidates */
    private function unknownOption(string $name, array $candidates): string
    {
        $ranked = [];
        foreach ($candidates as $candidate) {
            $ranked[$candidate] = levenshtein($name, $candidate);
        }
        asort($ranked, SORT_NUMERIC);
        $suggestion = array_key_first($ranked);
        $message = sprintf('Option "%s" is not defined.', $name);

        return $suggestion !== null && $ranked[$suggestion] <= max(2, (int) floor(strlen($name) / 2))
            ? $message . sprintf(' Did you mean "%s"?', $suggestion)
            : $message;
    }
}
