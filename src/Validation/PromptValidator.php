<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\Console\Exception\UsageException;
use Infocyph\ReqShield\Validator;

/** @internal */
final class PromptValidator
{
    /**
     * @param list<string> $rules
     * @param list<string> $sanitizers
     */
    public function validate(string $value, array $rules, array $sanitizers = []): string
    {
        if ($rules === []) {
            return $value;
        }
        $validator = Validator::make(['value' => $rules]);
        if ($sanitizers !== []) {
            $validator->setSanitizers(['value' => $sanitizers]);
        }
        $result = $validator->validate(['value' => $value]);
        if ($result->fails()) {
            throw new UsageException($result->firstError('value') ?? 'Invalid input.');
        }

        $validated = $result->typed()['value'] ?? $value;
        if (!is_scalar($validated) && !$validated instanceof \Stringable) {
            throw new UsageException('Validated prompt input must be scalar or stringable.');
        }

        return (string) $validated;
    }
}
