<?php

declare(strict_types=1);

namespace Infocyph\Console\Support;

/**
 * @internal
 */
final class ManifestValue
{
    private function __construct() {}

    public static function bool(mixed $value, string $field, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }
        if (!is_bool($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be boolean.', $field));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be an object map.', $field));
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException(sprintf(
                    'Manifest field "%s" must use string keys.',
                    $field,
                ));
            }
            $map[$key] = $item;
        }

        return $map;
    }

    /** @return list<array<string, mixed>> */
    public static function mapList(mixed $value, string $field): array
    {
        $maps = [];
        foreach (self::valueList($value, $field) as $index => $item) {
            $maps[] = self::map($item, $field . '.' . $index);
        }

        return $maps;
    }

    public static function nullableString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::string($value, $field);
    }

    public static function string(mixed $value, string $field, ?string $default = null): string
    {
        if ($value === null && $default !== null) {
            return $default;
        }
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be a string.', $field));
        }

        return $value;
    }

    /** @return list<string> */
    public static function stringList(mixed $value, string $field): array
    {
        $strings = [];
        foreach (self::valueList($value, $field) as $index => $item) {
            $strings[] = self::string($item, $field . '.' . $index);
        }

        return $strings;
    }

    /** @return list<mixed> */
    public static function valueList(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be a list.', $field));
        }

        return $value;
    }
}
