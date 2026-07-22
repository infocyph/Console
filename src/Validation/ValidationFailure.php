<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

final readonly class ValidationFailure
{
    public function __construct(
        public string $field,
        public string $rule,
        public string $message,
    ) {}

    /** @return array{field:string,rule:string,message:string} */
    public function toArray(): array
    {
        return ['field' => $this->field, 'rule' => $this->rule, 'message' => $this->message];
    }
}
