<?php

declare(strict_types=1);

use Infocyph\Console\Data\DBLayerCommandHistoryRepository;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Scheduling\DBLayerScheduleStateRepository;
use Infocyph\Console\Scheduling\ScheduleRun;
use Infocyph\Console\Scheduling\ScheduleRunStatus;
use Infocyph\Console\Validation\DBLayerDatabaseProvider;
use Infocyph\DBLayer\Connection\Connection;
use Infocyph\DBLayer\DB;

function consoleDBLayerConnection(): Connection
{
    DB::purge();

    return DB::addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ], 'console-test');
}

afterEach(function (): void {
    DB::purge();
});

test('DBLayer 2.2 provides Console database validation', function (): void {
    $connection = consoleDBLayerConnection();
    $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL, tenant TEXT NOT NULL)');
    $connection->statement(
        'INSERT INTO users (id, email, tenant) VALUES (?, ?, ?)',
        [1, 'user@example.com', 'acme'],
    );
    $provider = new DBLayerDatabaseProvider($connection);

    expect($provider->exists('users', 'email', 'user@example.com'))->toBeTrue()
        ->and($provider->exists('users', 'email', 'missing@example.com'))->toBeFalse()
        ->and($provider->compositeUnique('users', [
            'email' => 'new@example.com',
            'tenant' => 'acme',
        ]))->toBeTrue()
        ->and($provider->query('SELECT email FROM users WHERE id = ?', [1]))->toBe([
            ['email' => 'user@example.com'],
        ]);
});

test('DBLayer 2.2 persists command history and schedule outcomes', function (): void {
    $connection = consoleDBLayerConnection();
    $connection->statement(
        'CREATE TABLE console_command_history (id TEXT, command TEXT, started_at INTEGER, exit_code INTEGER, metadata TEXT)',
    );
    $connection->statement(
        'CREATE TABLE console_schedule_runs (id TEXT, command TEXT, scheduled_at INTEGER, finished_at INTEGER, exit_code INTEGER, status TEXT)',
    );

    (new DBLayerCommandHistoryRepository(connection: 'console-test'))->record(
        new CommandExecution('execution-1', 'reports:build', 100),
        0,
        ['source' => 'test'],
    );
    (new DBLayerScheduleStateRepository(connection: 'console-test'))->record(
        new ScheduleRun('schedule-1', 'reports:build', 100, 101, 0, ScheduleRunStatus::SKIPPED),
    );

    expect($connection->select('SELECT command, exit_code, metadata FROM console_command_history'))->toBe([
        [
            'command' => 'reports:build',
            'exit_code' => 0,
            'metadata' => '{"source":"test"}',
        ],
    ])->and($connection->select('SELECT command, exit_code, status FROM console_schedule_runs'))->toBe([
        [
            'command' => 'reports:build',
            'exit_code' => 0,
            'status' => 'skipped',
        ],
    ]);
});
