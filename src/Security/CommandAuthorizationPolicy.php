<?php

declare(strict_types=1);

namespace Infocyph\Console\Security;

use Infocyph\Console\Command\CommandContext;
use Infocyph\Console\Command\CommandDescriptor;

interface CommandAuthorizationPolicy
{
    public function authorize(CommandDescriptor $command, CommandContext $context): bool;
}
