<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Input\ArgumentCollection;
use Infocyph\Console\Input\OptionCollection;
use Infocyph\Console\Input\ParsedInput;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

/** @internal */
final class InputValidator
{
    private readonly ValidationCompiler $compiler;

    private ?ValidationManifest $manifest = null;

    public function __construct(
        ?DatabaseProvider $database = null,
        private readonly ?string $manifestPath = null,
    ) {
        $this->compiler = new ValidationCompiler($database);
    }

    public function validate(CommandDescriptor $command, ParsedInput $input): ParsedInput
    {
        if ($this->manifest === null && $this->manifestPath !== null) {
            $this->manifest = ValidationManifest::load($this->manifestPath);
        }
        $compiled = $this->compiler->compile($command, $this->manifest?->for($command->name()) ?? []);
        if ($compiled === null) {
            return $input;
        }
        $arguments = $input->arguments()->all();
        $options = $input->options()->all();
        $result = $compiled->validate(array_merge($arguments, $options));
        if (!$result->passes()) {
            throw new ValidationFailedException($result->failures);
        }
        $validated = $result->data;
        foreach ($compiled->fields() as $field => $_) {
            if (array_key_exists($field, $validated)) {
                if (array_key_exists($field, $arguments)) {
                    $arguments[$field] = $validated[$field];
                }
                if (array_key_exists($field, $options)) {
                    $options[$field] = $validated[$field];
                }
            }
        }

        return new ParsedInput(new ArgumentCollection($arguments), new OptionCollection($options), $input->tokens());
    }
}
