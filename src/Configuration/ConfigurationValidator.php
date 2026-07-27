<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

use Infocyph\Console\Validation\ValidationFailedException;
use Infocyph\Console\Validation\ValidationFailure;
use Infocyph\ReqShield\Validator;

final class ConfigurationValidator
{
    /**
     * @param array<string, mixed> $rules
     * @param array<string, array<int, (callable(): mixed)|string>|(callable(): mixed)|string> $sanitizers
     */
    public function validate(Configuration $configuration, array $rules, array $sanitizers = [], bool $strict = false): Configuration
    {
        if ($rules === []) {
            return $configuration;
        }
        if (!class_exists(Validator::class)) {
            throw new \LogicException(
                'Configuration validation requires infocyph/reqshield; install the package or remove the configuration rules.',
            );
        }
        $validator = Validator::make($rules);
        if ($sanitizers !== []) {
            $validator->setSanitizers($sanitizers);
        }
        if ($strict) {
            $validator->strict();
        }
        $result = $validator->validate($configuration->all());
        if ($result->fails()) {
            $failures = [];
            foreach ($result->toFlatErrors() as $failure) {
                $failures[] = new ValidationFailure(
                    $failure['field'],
                    $failure['rule'],
                    $failure['message'],
                );
            }

            throw new ValidationFailedException($failures);
        }

        return new ConfigurationLoader()->load([$result->typed()]);
    }
}
