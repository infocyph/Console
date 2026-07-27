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
        $values = [];

        foreach ($command->options() as $option) {
            $longOptions[$option->name()] = $option;
            $values[$option->name()] = $this->defaultValue($option);
            if ($option->shortName() !== null) {
                $shortOptions[$option->shortName()] = $option;
            }
        }

        $seen = [];
        $positionals = [];
        $endOfOptions = false;

        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; $index++) {
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

    private function booleanDefault(mixed $value, string $name): bool
    {
        if (is_string($value)) {
            return $this->boolean($value, $name);
        }
        if (!is_bool($value) && !is_int($value) && !is_float($value)) {
            throw new \LogicException(sprintf('Default value for "%s" must be boolean-compatible.', $name));
        }

        return (bool) $value;
    }

    private function configuredDefault(Argument|Option $definition): mixed
    {
        $environment = $definition->environmentVariable();
        if ($environment === null) {
            return $definition->defaultValue();
        }

        $value = getenv($environment);

        return $value === false ? $definition->defaultValue() : $value;
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
        $value = $this->configuredDefault($definition);

        if ($value === null) {
            return $value;
        }

        if ($definition instanceof Option && !$definition->acceptsValue()) {
            return $this->booleanDefault($value, $definition->name());
        }

        if ($definition instanceof Option && $definition->multipleValues()) {
            return $this->multipleOptionDefault($value, $definition);
        }

        if ($definition instanceof Argument && $definition->isVariadic()) {
            return array_map(
                fn(mixed $item): mixed => is_string($item)
                    ? $this->convert($item, $definition->valueType(), $definition->name())
                    : $item,
                $this->variadicDefault($value, $definition),
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

    /** @return list<mixed> */
    private function multipleOptionDefault(mixed $value, Option $definition): array
    {
        if (is_string($value)) {
            return [$this->convert($value, $definition->valueType(), $definition->name())];
        }
        if (!is_array($value)) {
            throw new \LogicException(sprintf('Default value for multiple option "%s" must be an array.', $definition->name()));
        }

        return array_values($value);
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
     * @param-out array<string, true> $seen
     * @param-out array<string, mixed> $values
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
            $this->storeOption($option, false, $seen, $values);

            return;
        }

        if (!$option->acceptsValue()) {
            if ($inlineValue !== null) {
                throw new UsageException(sprintf('Option "--%s" does not accept a value.', $name));
            }
            $this->storeOption($option, true, $seen, $values);

            return;
        }

        if ($inlineValue === null) {
            $index++;
            if (!array_key_exists($index, $tokens)) {
                throw new UsageException(sprintf('Option "--%s" requires a value.', $name));
            }
            $inlineValue = $tokens[$index];
        }

        $this->storeOption(
            $option,
            $this->convert($inlineValue, $option->valueType(), '--' . $name),
            $seen,
            $values,
        );
    }

    /**
     * @param list<string> $tokens
     * @param array<string, Option> $options
     * @param array<string, true> $seen
     * @param array<string, mixed> $values
     * @param-out array<string, true> $seen
     * @param-out array<string, mixed> $values
     */
    private function parseShortOptions(string $token, array $tokens, int &$index, array $options, array &$seen, array &$values): void
    {
        $shortcuts = substr($token, 1);
        $shortcutCount = strlen($shortcuts);
        for ($offset = 0; $offset < $shortcutCount; $offset++) {
            $shortcut = $shortcuts[$offset];
            $option = $options[$shortcut] ?? null;
            if ($option === null) {
                throw new UsageException($this->unknownOption('-' . $shortcut, array_map(static fn(string $candidate): string => '-' . $candidate, array_keys($options))));
            }

            if (!$option->acceptsValue()) {
                $this->storeOption($option, true, $seen, $values);

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

            $this->storeOption(
                $option,
                $this->convert($value, $option->valueType(), '-' . $shortcut),
                $seen,
                $values,
            );

            return;
        }
    }

    /**
     * @param array<string, true> $seen
     * @param array<string, mixed> $values
     * @param-out array<string, true> $seen
     * @param-out array<string, mixed> $values
     */
    private function storeOption(Option $option, mixed $value, array &$seen, array &$values): void
    {
        $name = $option->name();
        if (isset($seen[$name]) && !$option->multipleValues()) {
            throw new UsageException(sprintf('Option "--%s" may not be used more than once.', $name));
        }

        $seen[$name] = true;
        if ($option->multipleValues()) {
            $existing = $values[$name] ?? [];
            if (!is_array($existing)) {
                throw new \LogicException(sprintf('Multiple option "%s" must be initialized with an array.', $name));
            }
            $existing[] = $value;
            $values[$name] = $existing;

            return;
        }

        $values[$name] = $value;
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

    /** @return list<mixed> */
    private function variadicDefault(mixed $value, Argument $definition): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new \LogicException(sprintf('Default value for variadic argument "%s" must be scalar, stringable, or an array.', $definition->name()));
        }

        $delimiter = $definition->environmentDelimiter();
        if ($delimiter === null) {
            throw new \LogicException('Variadic argument values require an explicit environment delimiter.');
        }

        $string = (string) $value;

        return $delimiter === '' ? [$string] : explode($delimiter, $string);
    }
}
