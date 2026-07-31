<?php

declare(strict_types=1);

use Infocyph\Console\Data\DBLayerCommandHistoryRepository;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Scheduling\DBLayerScheduleStateRepository;
use Infocyph\Console\Scheduling\ScheduleRun;
use Infocyph\Console\Scheduling\ScheduleRunStatus;
use Infocyph\Console\Validation\DBLayerDatabaseProvider;
use Infocyph\DBLayer\Connection\Connection;
use Infocyph\DBLayer\Connection\ConnectionConfig;
use Infocyph\DBLayer\DB;

/**
 * @return array<string, array<string, mixed>>
 */
function consoleDatabaseConfigs(): array
{
    static $configs;

    if (is_array($configs)) {
        return $configs;
    }
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('Console database integration tests require PDO SQLite.');
    }

    $configs = [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ];
    foreach (['mysql', 'pgsql'] as $driver) {
        $config = consoleServiceDatabaseConfig($driver);
        if ($config !== null) {
            consoleAssertDatabaseAvailable($driver, $config);
            $configs[$driver] = $config;
        }
    }

    return $configs;
}

/**
 * @param array<string, mixed> $config
 */
function consoleAssertDatabaseAvailable(string $driver, array $config): void
{
    if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException(sprintf(
            'The %s service is configured but its PDO driver is unavailable.',
            $driver,
        ));
    }

    try {
        $connection = new Connection(ConnectionConfig::fromArray($config));
        $connection->select('SELECT 1');
    } catch (Throwable $exception) {
        throw new RuntimeException(
            sprintf('The configured %s service is unavailable: %s', $driver, $exception->getMessage()),
            previous: $exception,
        );
    }
}

/**
 * @return array<string, mixed>|null
 */
function consoleServiceDatabaseConfig(string $driver): ?array
{
    $database = consoleDatabaseEnvironment([
        $driver === 'mysql' ? 'CONSOLE_MYSQL_DATABASE' : 'CONSOLE_PGSQL_DATABASE',
        $driver === 'mysql' ? 'DBLAYER_MYSQL_DATABASE' : 'DBLAYER_PGSQL_DATABASE',
        'IC_SERVICE_DATABASE',
    ]);
    $username = consoleDatabaseEnvironment([
        $driver === 'mysql' ? 'CONSOLE_MYSQL_USERNAME' : 'CONSOLE_PGSQL_USERNAME',
        $driver === 'mysql' ? 'DBLAYER_MYSQL_USERNAME' : 'DBLAYER_PGSQL_USERNAME',
        'IC_SERVICE_USERNAME',
    ]);
    if ($database === null || $username === null) {
        return null;
    }

    return [
        'driver' => $driver,
        'host' => consoleDatabaseEnvironment([
            $driver === 'mysql' ? 'CONSOLE_MYSQL_HOST' : 'CONSOLE_PGSQL_HOST',
            $driver === 'mysql' ? 'DBLAYER_MYSQL_HOST' : 'DBLAYER_PGSQL_HOST',
        ]) ?? '127.0.0.1',
        'port' => (int) (consoleDatabaseEnvironment([
            $driver === 'mysql' ? 'CONSOLE_MYSQL_PORT' : 'CONSOLE_PGSQL_PORT',
            $driver === 'mysql' ? 'DBLAYER_MYSQL_PORT' : 'DBLAYER_PGSQL_PORT',
        ]) ?? ($driver === 'mysql' ? '3306' : '5432')),
        'database' => $database,
        'username' => $username,
        'password' => consoleDatabaseEnvironment([
            $driver === 'mysql' ? 'CONSOLE_MYSQL_PASSWORD' : 'CONSOLE_PGSQL_PASSWORD',
            $driver === 'mysql' ? 'DBLAYER_MYSQL_PASSWORD' : 'DBLAYER_PGSQL_PASSWORD',
            'IC_SERVICE_PASSWORD',
        ]) ?? '',
        'options' => [PDO::ATTR_TIMEOUT => 3],
    ];
}

/**
 * @param list<string> $names
 */
function consoleDatabaseEnvironment(array $names): ?string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return null;
}

function consoleDBLayerConnection(string $driver): Connection
{
    DB::purge();
    $config = consoleDatabaseConfigs()[$driver] ?? null;
    if ($config === null) {
        throw new RuntimeException(sprintf('Database driver %s is not configured.', $driver));
    }

    return DB::addConnection($config, 'console-test');
}

/**
 * @param list<string> $tables
 */
function consoleDropDatabaseTables(Connection $connection, array $tables): void
{
    foreach ($tables as $table) {
        $connection->statement(sprintf('DROP TABLE IF EXISTS %s', $table));
    }
}

afterEach(function (): void {
    DB::purge();
});

dataset('console_database_drivers', static fn(): array => array_keys(consoleDatabaseConfigs()));

test('DBLayer 3.0 provides Console database validation', function (string $driver): void {
    $connection = consoleDBLayerConnection($driver);
    $table = 'console_validation_users';
    consoleDropDatabaseTables($connection, [$table]);

    try {
        $connection->statement(sprintf(
            'CREATE TABLE %s (id BIGINT PRIMARY KEY, email VARCHAR(255) NOT NULL, tenant VARCHAR(255) NOT NULL)',
            $table,
        ));
        $connection->statement(
            sprintf('INSERT INTO %s (id, email, tenant) VALUES (?, ?, ?)', $table),
            [1, 'user@example.com', 'acme'],
        );
        $provider = new DBLayerDatabaseProvider($connection);

        expect($provider->exists($table, 'email', 'user@example.com'))->toBeTrue()
            ->and($provider->exists($table, 'email', 'missing@example.com'))->toBeFalse()
            ->and($provider->compositeUnique($table, [
                'email' => 'new@example.com',
                'tenant' => 'acme',
            ]))->toBeTrue()
            ->and($provider->batchExistsCheck($table, [
                'email' => ['field' => 'email', 'value' => 'user@example.com'],
                'tenant' => ['field' => 'tenant', 'value' => 'missing'],
            ]))->toBe(['tenant'])
            ->and($provider->batchUniqueCheck($table, [
                'email' => ['field' => 'email', 'value' => 'user@example.com'],
                'tenant' => ['field' => 'tenant', 'value' => 'new'],
            ]))->toBe(['email'])
            ->and($provider->query(sprintf('SELECT email FROM %s WHERE id = ?', $table), [1]))->toBe([
                ['email' => 'user@example.com'],
            ]);
    } finally {
        consoleDropDatabaseTables($connection, [$table]);
    }
})->with('console_database_drivers');

test('DBLayer 3.0 persists command history and schedule outcomes', function (string $driver): void {
    $connection = consoleDBLayerConnection($driver);
    $tables = ['console_command_history', 'console_schedule_runs'];
    consoleDropDatabaseTables($connection, $tables);

    try {
        $connection->statement(
            'CREATE TABLE console_command_history (id VARCHAR(64), command VARCHAR(255), started_at BIGINT, exit_code INTEGER, metadata TEXT)',
        );
        $connection->statement(
            'CREATE TABLE console_schedule_runs (id VARCHAR(64), command VARCHAR(255), scheduled_at BIGINT, finished_at BIGINT, exit_code INTEGER, status VARCHAR(32))',
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
    } finally {
        consoleDropDatabaseTables($connection, $tables);
    }
})->with('console_database_drivers');
