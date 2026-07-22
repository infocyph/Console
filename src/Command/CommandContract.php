<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

interface CommandContract
{
    public static function define(CommandDefinition $command): void;

    public function run(CommandContext $context): int;
}
