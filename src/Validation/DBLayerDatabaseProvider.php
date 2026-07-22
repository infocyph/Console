<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\DBLayer\Connection\Connection;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

/** @internal */
final readonly class DBLayerDatabaseProvider implements DatabaseProvider
{
    public function __construct(private Connection $connection) {}

    public function batchExistsCheck(string $table, array $checks): array
    {
        $failed = [];
        foreach ($checks as $column => $check) {
            [$field, $name, $value, $ignoreId] = $this->check($column, $check);
            if (!$this->exists($table, $name, $value, $ignoreId)) {
                $failed[] = $field;
            }
        }

        return $failed;
    }

    public function batchUniqueCheck(string $table, array $checks): array
    {
        $failed = [];
        foreach ($checks as $column => $check) {
            [$field, $name, $value, $ignoreId] = $this->check($column, $check);
            if ($this->exists($table, $name, $value, $ignoreId)) {
                $failed[] = $field;
            }
        }

        return $failed;
    }

    public function compositeUnique(string $table, array $columns, ?int $ignoreId = null): bool
    {
        $query = $this->connection->table($this->identifier($table));
        foreach ($columns as $column => $value) {
            $query->where($this->identifier((string) $column), '=', $value);
        }
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return !$query->exists();
    }

    public function exists(string $table, string $column, mixed $value, ?int $ignoreId = null): bool
    {
        $query = $this->connection->table($this->identifier($table))->where($this->identifier($column), '=', $value);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function query(string $query, array $params = []): array
    {
        return $this->connection->select($query, $params);
    }

    /** @return array{int|string,string,mixed,?int} */
    private function check(int|string $fallback, mixed $check): array
    {
        if (is_array($check)) {
            $column = (string) ($check['column'] ?? $fallback);
            $value = $check['value'] ?? null;
            $field = $check['field'] ?? $value;
            $ignore = isset($check['ignore_id']) ? (int) $check['ignore_id'] : null;

            return [$field, $this->identifier($column), $value, $ignore];
        }

        return [$check, $this->identifier((string) $fallback), $check, null];
    }

    private function identifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid database identifier "%s".', $identifier));
        }

        return $identifier;
    }
}
