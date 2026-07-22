<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

final readonly class ValidationResult
{
    /** @param array<string, mixed> $data @param list<ValidationFailure> $failures */
    public function __construct(public array $data, public array $failures = []) {}

    /** @return list<array{field:string,rule:string,message:string}> */
    public function failureData(): array
    {
        return array_map(static fn(ValidationFailure $failure): array => $failure->toArray(), $this->failures);
    }

    public function passes(): bool
    {
        return $this->failures === [];
    }
}
