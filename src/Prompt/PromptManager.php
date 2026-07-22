<?php

declare(strict_types=1);

namespace Infocyph\Console\Prompt;

use Infocyph\Console\Exception\UsageException;
use Infocyph\Console\Terminal\Keyboard;
use Infocyph\Console\Terminal\RawMode;
use Infocyph\Console\Validation\PromptValidator;

final class PromptManager
{
    private readonly \Closure $write;

    private ?Keyboard $keyboard = null;

    public function __construct(
        private readonly InputReader $input,
        callable $write,
        private bool $interactive = true,
    ) {
        $this->write = \Closure::fromCallable($write);
        if ($input instanceof StreamInputReader) {
            $this->keyboard = new Keyboard($input, new RawMode());
        }
    }

    /** @param array<string, string> $options */
    public function autocomplete(string $label, array $options, ?string $default = null): string
    {
        return $this->search($label, $options, $default);
    }

    public function confirm(string $label, bool $default = false): bool
    {
        $value = strtolower($this->ask($label . ' [yes/no]', $default ? 'yes' : 'no', true));

        return match ($value) {
            'y', 'yes', '1', 'true' => true, 'n', 'no', '0', 'false' => false, default => throw new UsageException(sprintf('%s must be answered yes or no.', $label)),
        };
    }

    /** @param array<string, array{type?: string, label?: string, default?: mixed, required?: bool, options?: array<string, string>}> $fields @return array<string, mixed> */
    public function form(array $fields): array
    {
        $values = [];
        foreach ($fields as $name => $field) {
            $label = $field['label'] ?? $name;
            $type = $field['type'] ?? 'text';
            $default = $field['default'] ?? null;
            $values[$name] = match ($type) {
                'number' => $this->number($label, is_int($default) || is_float($default) ? $default : null),
                'confirm' => $this->confirm($label, (bool) $default),
                'select' => $this->select($label, $field['options'] ?? [], is_string($default) ? $default : null),
                'multi_select' => $this->multiSelect($label, $field['options'] ?? [], is_array($default) ? $default : []),
                'password' => $this->password($label, (bool) ($field['required'] ?? true)),
                default => $this->text($label, is_string($default) ? $default : null, (bool) ($field['required'] ?? false)),
            };
        }

        return $values;
    }

    public function interactive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }

    /** @param array<string,string> $options @return array<string,string> */
    public function matchingOptions(array $options, string $query, int $visibleLimit = 10): array
    {
        $query = strtolower($query);
        $matches = array_filter($options, static fn(string $value, string $key): bool => $query === '' || str_contains(strtolower($key . ' ' . $value), $query), ARRAY_FILTER_USE_BOTH);

        return array_slice($matches, 0, max(1, $visibleLimit), true);
    }

    /** @param array<string, string> $options @return list<string> */
    public function multiSelect(string $label, array $options, array $default = []): array
    {
        $this->writeOptions($options);
        $value = $this->ask($label . ' (comma separated)', implode(',', $default), false);
        $selected = $value === '' ? [] : array_values(array_unique(array_map(trim(...), explode(',', $value))));
        foreach ($selected as $item) {
            if (!array_key_exists($item, $options)) {
                throw new UsageException(sprintf('"%s" is not a valid selection for %s.', $item, $label));
            }
        }

        return $selected;
    }

    public function number(string $label, int|float|null $default = null, ?callable $validate = null): int|float
    {
        $value = $this->ask($label, $default === null ? null : (string) $default, true, $validate);
        if (!is_numeric($value)) {
            throw new UsageException(sprintf('%s must be a number.', $label));
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    /** @param array<string,string> $options */
    public function optionViewport(array $options, int $maximumVisible = 10): OptionViewport
    {
        return new OptionViewport($options, $maximumVisible);
    }

    public function password(string $label, bool $required = true, ?callable $validate = null): string
    {
        return $this->ask($label, null, $required, $validate, [], true);
    }

    public function path(string $label, ?string $default = null, bool $mustExist = false): string
    {
        $path = $this->ask($label, $default, true);
        if ($mustExist && !file_exists($path)) {
            throw new UsageException(sprintf('Path "%s" does not exist.', $path));
        }

        return $path;
    }

    /** @param array<string, string> $options */
    public function search(string $label, array $options, ?string $default = null, ?string $placeholder = null, ?string $hint = null, int $visibleLimit = 10): string
    {
        $query = strtolower($this->ask($label, $default, true, placeholder: $placeholder, hint: $hint));
        $matches = $this->matchingOptions($options, $query, $visibleLimit);
        if ($matches !== []) {
            return array_key_first($matches);
        }

        throw new UsageException(sprintf('No result matched "%s".', $query));
    }

    /** @param array<string, string> $options */
    public function select(string $label, array $options, ?string $default = null): string
    {
        if (!$this->interactive) {
            $value = $this->ask($label, $default, true);
            if (!array_key_exists($value, $options)) {
                throw new UsageException(sprintf('"%s" is not a valid selection for %s.', $value, $label));
            }

            return $value;
        }
        $viewport = $this->optionViewport($options);
        $this->keyboard?->begin();

        try {
            while (true) {
                foreach ($viewport->frame()->lines as $line) {
                    ($this->write)($line->text);
                }
                ($this->write)($label . ' [enter to select]:');
                $value = $this->keyboard?->read() ?? $this->input->read();
                if ($value === null) {
                    throw new PromptCancelled('Input was cancelled.');
                }
                $value = (string) $value;
                if (in_array($value, ["\033[A", 'up'], true)) {
                    $viewport->move(-1);

                    continue;
                }
                if (in_array($value, ["\033[B", 'down'], true)) {
                    $viewport->move(1);

                    continue;
                }
                if (in_array($value, ['', 'enter'], true)) {
                    return $default ?? $viewport->selected() ?? throw new UsageException('No options are available.');
                }
                if (!array_key_exists($value, $options)) {
                    ($this->write)('[ERROR] ' . sprintf('"%s" is not a valid selection for %s.', $value, $label));

                    continue;
                }

                return $value;
            }
        } finally {
            $this->keyboard?->restore();
        }
    }

    /** @param list<string> $sanitize */
    public function text(string $label, ?string $default = null, bool $required = false, ?callable $validate = null, array $sanitize = [], array $rules = [], ?string $placeholder = null, ?string $hint = null): string
    {
        return $this->ask($label, $default, $required, $validate, $sanitize, false, $rules, $placeholder, $hint);
    }

    /** @param list<string> $sanitize */
    public function textArea(string $label, ?string $default = null, bool $required = false, ?callable $validate = null, array $sanitize = [], array $rules = [], ?string $placeholder = null, ?string $hint = null): string
    {
        return $this->ask($label, $default, $required, $validate, $sanitize, false, $rules, $placeholder, $hint);
    }

    /** @param list<string> $sanitize */
    private function ask(string $label, ?string $default, bool $required, ?callable $validate = null, array $sanitize = [], bool $secret = false, array $rules = [], ?string $placeholder = null, ?string $hint = null): string
    {
        if (!$this->interactive) {
            if ($default === null) {
                throw new UsageException(sprintf('%s requires input, but interaction is disabled.', $label));
            }

            return $this->semanticValidate($this->sanitize($default, $sanitize), $rules, $sanitize);
        }
        do {
            if ($hint !== null && $hint !== '') {
                ($this->write)($hint);
            }
            $suffix = $default === null ? ($placeholder === null ? ':' : ' (' . $placeholder . '):') : ' [' . $default . ']:';
            ($this->write)($label . $suffix);
            $raw = $this->input->read($secret);
            if ($raw === null) {
                throw new PromptCancelled('Input was cancelled.');
            }
            $value = $this->sanitize((string) $raw === '' && $default !== null ? $default : (string) $raw, $sanitize);
            $error = $required && $value === '' ? 'A value is required.' : ($validate !== null ? $validate($value) : null);
            if (!is_string($error) || $error === '') {
                try {
                    $value = $this->semanticValidate($value, $rules, $sanitize);
                } catch (UsageException $exception) {
                    $error = $exception->getMessage();
                }
            }
            if (is_string($error) && $error !== '') {
                ($this->write)('[ERROR] ' . $error);
            }
        } while (is_string($error) && $error !== '');

        return $value;
    }

    /** @param list<string> $sanitize */
    private function sanitize(string $value, array $sanitize): string
    {
        foreach ($sanitize as $operation) {
            $value = match ($operation) {
                'trim' => trim($value), 'lowercase' => strtolower($value), 'uppercase' => strtoupper($value), default => $value,
            };
        }

        return $value;
    }

    /** @param list<string> $rules @param list<string> $sanitize */
    private function semanticValidate(string $value, array $rules, array $sanitize): string
    {
        return $rules === [] ? $value : new PromptValidator()->validate($value, $rules, $sanitize);
    }

    /** @param array<string, string> $options */
    private function writeOptions(array $options): void
    {
        foreach ($options as $key => $value) {
            ($this->write)('  ' . $key . '  ' . $value);
        }
    }
}
