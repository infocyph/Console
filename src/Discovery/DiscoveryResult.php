<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandContract;

final readonly class DiscoveryResult
{
    /** @param list<class-string<CommandContract>> $commands */
    public function __construct(public array $commands) {}
}
