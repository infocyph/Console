<?php

declare(strict_types=1);

namespace Infocyph\Console\Omnibus;

use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Exception\UsageException;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Input\ValueType;
use Infocyph\Omnibus\Consumer\Command\ConsumeRequest;
use Infocyph\Omnibus\Consumer\Command\ConsumerTask;

final class ConsumeCommand extends Command
{
    public const string NAME = 'queue:consume';

    public function __construct(private readonly ConsumerTask $consumer) {}

    public static function define(CommandDefinition $command): void
    {
        $command
            ->name(self::NAME)
            ->description('Consume one bounded batch of queued messages.')
            ->option(
                Option::value('queue', 'default')
                    ->description('Queue name. Example: emails.'),
            )
            ->option(
                Option::value('limit')
                    ->type(ValueType::INTEGER)
                    ->default(1)
                    ->description('Maximum messages to reserve. Example: 100.'),
            )
            ->option(
                Option::value('visibility')
                    ->type(ValueType::FLOAT)
                    ->default(60.0)
                    ->description('Reservation visibility timeout in seconds. Example: 90.0.'),
            );
    }

    protected function handle(): int
    {
        try {
            $request = new ConsumeRequest(
                $this->options()->string('queue'),
                $this->options()->int('limit'),
                $this->options()->float('visibility'),
            );
        } catch (\InvalidArgumentException $exception) {
            throw new UsageException($exception->getMessage(), previous: $exception);
        }

        $result = $this->consumer->run($request);
        $this->io()->details([
            'received' => $result->received,
            'succeeded' => $result->succeeded,
            'released' => $result->released,
            'failed' => $result->failed,
        ]);

        return ExitCode::SUCCESS;
    }
}
