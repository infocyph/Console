<?php

declare(strict_types=1);

namespace Infocyph\Console\Data;

use Infocyph\Console\Identity\CommandExecution;
use Infocyph\DBLayer\DB;

final readonly class DBLayerCommandHistoryRepository implements CommandHistoryRepository
{
    public function __construct(private string $table = 'console_command_history', private ?string $connection = null) {}

    public function record(CommandExecution $execution, int $exitCode, array $metadata = []): void
    {
        DB::table($this->table, $this->connection)->insert([
            'id' => $execution->id,
            'command' => $execution->command,
            'started_at' => $execution->startedAt,
            'exit_code' => $exitCode,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
