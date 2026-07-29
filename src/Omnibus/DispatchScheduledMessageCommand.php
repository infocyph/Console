<?php

declare(strict_types=1);

namespace Infocyph\Console\Omnibus;

use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Input\Argument;
use Infocyph\Omnibus\Scheduling\ScheduledMessageDispatcher;

final class DispatchScheduledMessageCommand extends Command
{
    public const string NAME = 'schedule:dispatch-message';

    public function __construct(private readonly ScheduledMessageDispatcher $messages) {}

    public static function define(CommandDefinition $command): void
    {
        $command
            ->name(self::NAME)
            ->description('Dispatch a scheduled Omnibus message by its factory key.')
            ->argument(
                Argument::required('factory')
                    ->description('Registered message factory key. Example: reports.daily.'),
            );
    }

    protected function handle(): int
    {
        $this->messages->dispatch($this->arguments()->string('factory'));

        return ExitCode::SUCCESS;
    }
}
