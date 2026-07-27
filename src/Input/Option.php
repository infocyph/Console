<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

use Infocyph\Console\Support\ManifestValue;

final readonly class Option
{
    /**
     * @param list<string> $sanitizers
     * @param list<string> $rules
     */
    private function __construct(
        private string $name,
        private bool $acceptsValue,
        private bool $multiple = false,
        private bool $negatable = false,
        private ?string $shortcut = null,
        private ValueType $type = ValueType::STRING,
        private mixed $default = null,
        private ?string $environmentVariable = null,
        private string $description = '',
        private array $sanitizers = [],
        private array $rules = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1 || str_starts_with($name, 'no-')) {
            throw new \InvalidArgumentException('Option names must use lowercase letters, digits, and hyphens and cannot start with "no-".');
        }
        if (!$acceptsValue && $type !== ValueType::BOOLEAN) {
            throw new \LogicException('Flag options must use the boolean type.');
        }
        if (!$acceptsValue && !is_bool($default)) {
            throw new \InvalidArgumentException('Flag defaults must be boolean.');
        }
        if ($multiple && !is_array($default)) {
            throw new \InvalidArgumentException('Multiple option defaults must be arrays.');
        }
        if (!$multiple && is_array($default)) {
            throw new \InvalidArgumentException('Only multiple options may use array defaults.');
        }
        self::assertDefault($default, $type);
    }

    public static function flag(string $name): self
    {
        return new self($name, false, type: ValueType::BOOLEAN, default: false);
    }

    /** @param array<string,mixed> $data */
    public static function fromManifest(array $data): self
    {
        return new self(
            ManifestValue::string($data['name'] ?? null, 'option.name'),
            ManifestValue::bool($data['accepts_value'] ?? null, 'option.accepts_value'),
            ManifestValue::bool($data['multiple'] ?? null, 'option.multiple'),
            ManifestValue::bool($data['negatable'] ?? null, 'option.negatable'),
            ManifestValue::nullableString($data['shortcut'] ?? null, 'option.shortcut'),
            ValueType::from(ManifestValue::string($data['type'] ?? null, 'option.type', 'string')),
            $data['default'] ?? null,
            ManifestValue::nullableString($data['environment'] ?? null, 'option.environment'),
            ManifestValue::string($data['description'] ?? null, 'option.description', ''),
            ManifestValue::stringList($data['sanitizers'] ?? [], 'option.sanitizers'),
            ManifestValue::stringList($data['rules'] ?? [], 'option.rules'),
        );
    }

    public static function multiple(string $name): self
    {
        return new self($name, true, multiple: true, default: []);
    }

    public static function value(string $name, mixed $default = null): self
    {
        return new self($name, true, default: $default);
    }

    public function acceptsValue(): bool
    {
        return $this->acceptsValue;
    }

    public function default(mixed $default): self
    {
        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $this->shortcut, $this->type, $default, $this->environmentVariable, $this->description, $this->sanitizers, $this->rules);
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    public function description(string $description): self
    {
        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $this->shortcut, $this->type, $this->default, $this->environmentVariable, $description, $this->sanitizers, $this->rules);
    }

    public function descriptionText(): string
    {
        return $this->description;
    }

    public function env(string $variable): self
    {
        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $this->shortcut, $this->type, $this->default, $variable, $this->description, $this->sanitizers, $this->rules);
    }

    public function environmentVariable(): ?string
    {
        return $this->environmentVariable;
    }

    public function isNegatable(): bool
    {
        return $this->negatable;
    }

    public function multipleValues(): bool
    {
        return $this->multiple;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function negatable(bool $negatable = true): self
    {
        if ($negatable && $this->acceptsValue) {
            throw new \LogicException('Only flag options can be negatable.');
        }

        return new self($this->name, $this->acceptsValue, $this->multiple, $negatable, $this->shortcut, $this->type, $this->default, $this->environmentVariable, $this->description, $this->sanitizers, $this->rules);
    }

    /** @param list<string> $rules */
    public function rules(array $rules): self
    {
        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $this->shortcut, $this->type, $this->default, $this->environmentVariable, $this->description, $this->sanitizers, $rules);
    }

    /** @return list<string> */
    public function ruleset(): array
    {
        return $this->rules;
    }

    /** @param list<string> $sanitizers */
    public function sanitize(array $sanitizers): self
    {
        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $this->shortcut, $this->type, $this->default, $this->environmentVariable, $this->description, $sanitizers, $this->rules);
    }

    /** @return list<string> */
    public function sanitizers(): array
    {
        return $this->sanitizers;
    }

    public function shortcut(string $shortcut): self
    {
        if (preg_match('/^[A-Za-z0-9]$/', $shortcut) !== 1) {
            throw new \InvalidArgumentException('An option shortcut must be one alphanumeric character.');
        }

        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $shortcut, $this->type, $this->default, $this->environmentVariable, $this->description, $this->sanitizers, $this->rules);
    }

    public function shortName(): ?string
    {
        return $this->shortcut;
    }

    /** @return array<string,mixed> */
    public function toManifest(): array
    {
        return ['name' => $this->name, 'accepts_value' => $this->acceptsValue, 'multiple' => $this->multiple, 'negatable' => $this->negatable, 'shortcut' => $this->shortcut, 'type' => $this->type->value, 'default' => $this->default, 'environment' => $this->environmentVariable, 'description' => $this->description, 'sanitizers' => $this->sanitizers, 'rules' => $this->rules];
    }

    public function type(ValueType $type): self
    {
        return new self($this->name, $this->acceptsValue, $this->multiple, $this->negatable, $this->shortcut, $type, $this->default, $this->environmentVariable, $this->description, $this->sanitizers, $this->rules);
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
