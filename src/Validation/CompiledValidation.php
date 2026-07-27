<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\ReqShield\CompiledValidator;

/** @internal */
final readonly class CompiledValidation
{
    /** @param array<string, true> $fields */
    public function __construct(private CompiledValidator $validator, private array $fields) {}

    /** @return array<string, true> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @param array<string, mixed> $values */
    public function validate(array $values): ValidationResult
    {
        $result = $this->validator->validate($values);
        if ($result->passes()) {
            $data = [];
            foreach ($result->typed() as $key => $value) {
                if (!is_string($key)) {
                    throw new \UnexpectedValueException('Validated input must use string field names.');
                }
                $data[$key] = $value;
            }

            return new ValidationResult($data);
        }
        $failures = [];
        foreach ($result->toFlatErrors() as $failure) {
            $failures[] = new ValidationFailure($failure['field'], $failure['rule'], $failure['message']);
        }

        return new ValidationResult([], $failures);
    }
}
