<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

final readonly class Argument
{
    /**
     * @param list<string> $sanitizers
     * @param list<string> $rules
     */
    private function __construct(
        private string $name,
        private bool $required,
        private bool $variadic = false,
        private ValueType $type = ValueType::STRING,
        private mixed $default = null,
        private ?string $environmentVariable = null,
        private ?string $environmentDelimiter = null,
        private string $description = '',
        private array $sanitizers = [],
        private array $rules = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('Argument names must use lowercase letters, digits, underscores, or hyphens.');
        }
        if ($variadic && !is_array($default)) {
            throw new \InvalidArgumentException('Variadic argument defaults must be arrays.');
        }
        if (!$variadic && is_array($default)) {
            throw new \InvalidArgumentException('Only variadic arguments may use array defaults.');
        }
        if ($variadic && $environmentVariable !== null && $environmentDelimiter === null) {
            throw new \InvalidArgumentException('Variadic argument environment values require an explicit delimiter.');
        }
        self::assertDefault($default, $type);
    }

    /** @param array<string,mixed> $data */
    public static function fromManifest(array $data): self
    {
        return new self((string) $data['name'], (bool) $data['required'], (bool) ($data['variadic'] ?? false), ValueType::from((string) ($data['type'] ?? 'string')), $data['default'] ?? null, isset($data['environment']) ? (string) $data['environment'] : null, isset($data['environment_delimiter']) ? (string) $data['environment_delimiter'] : null, (string) ($data['description'] ?? ''), $data['sanitizers'] ?? [], $data['rules'] ?? []);
    }

    public static function optional(string $name, mixed $default = null): self
    {
        return new self($name, false, default: $default);
    }

    public static function required(string $name): self
    {
        return new self($name, true);
    }

    public static function variadic(string $name, bool $required = false): self
    {
        return new self($name, $required, variadic: true, default: []);
    }

    public function default(mixed $default): self
    {
        return new self($this->name, $this->required, $this->variadic, $this->type, $default, $this->environmentVariable, $this->environmentDelimiter, $this->description, $this->sanitizers, $this->rules);
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    public function description(string $description): self
    {
        return new self($this->name, $this->required, $this->variadic, $this->type, $this->default, $this->environmentVariable, $this->environmentDelimiter, $description, $this->sanitizers, $this->rules);
    }

    public function descriptionText(): string
    {
        return $this->description;
    }

    public function env(string $variable, ?string $delimiter = null): self
    {
        return new self($this->name, $this->required, $this->variadic, $this->type, $this->default, $variable, $delimiter, $this->description, $this->sanitizers, $this->rules);
    }

    public function environmentDelimiter(): ?string
    {
        return $this->environmentDelimiter;
    }

    public function environmentVariable(): ?string
    {
        return $this->environmentVariable;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isVariadic(): bool
    {
        return $this->variadic;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @param list<string> $rules */
    public function rules(array $rules): self
    {
        return new self($this->name, $this->required, $this->variadic, $this->type, $this->default, $this->environmentVariable, $this->environmentDelimiter, $this->description, $this->sanitizers, $rules);
    }

    /** @return list<string> */
    public function ruleset(): array
    {
        return $this->rules;
    }

    /** @param list<string> $sanitizers */
    public function sanitize(array $sanitizers): self
    {
        return new self($this->name, $this->required, $this->variadic, $this->type, $this->default, $this->environmentVariable, $this->environmentDelimiter, $this->description, $sanitizers, $this->rules);
    }

    /** @return list<string> */
    public function sanitizers(): array
    {
        return $this->sanitizers;
    }

    /** @return array<string,mixed> */
    public function toManifest(): array
    {
        return ['name' => $this->name, 'required' => $this->required, 'variadic' => $this->variadic, 'type' => $this->type->value, 'default' => $this->default, 'environment' => $this->environmentVariable, 'environment_delimiter' => $this->environmentDelimiter, 'description' => $this->description, 'sanitizers' => $this->sanitizers, 'rules' => $this->rules];
    }

    public function type(ValueType $type): self
    {
        return new self($this->name, $this->required, $this->variadic, $type, $this->default, $this->environmentVariable, $this->environmentDelimiter, $this->description, $this->sanitizers, $this->rules);
    }

    public function valueType(): ValueType
    {
        return $this->type;
    }

    private static function assertDefault(mixed $value, ValueType $type): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertDefault($item, $type);
            }

            return;
        }

        $valid = match ($type) {
            ValueType::STRING => is_string($value),
            ValueType::INTEGER => is_int($value) || (is_string($value) && preg_match('/^[+-]?\d+$/D', $value) === 1),
            ValueType::FLOAT => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            ValueType::BOOLEAN => is_bool($value) || (is_string($value) && in_array(strtolower($value), ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'], true)),
        };

        if (!$valid) {
            throw new \InvalidArgumentException(sprintf('%s defaults must match their declared type.', ucfirst($type->value)));
        }
    }
}
