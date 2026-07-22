<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

/** @internal */
final class TextWidth
{
    public static function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - self::width($text)));
    }

    public static function truncate(string $text, int $width): string
    {
        if ($width < 1 || self::width($text) <= $width) {
            return $width < 1 ? '' : $text;
        }

        $truncated = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (self::width($truncated . $character) > $width) {
                break;
            }
            $truncated .= $character;
        }

        return $truncated;
    }

    public static function width(string $text): int
    {
        return function_exists('mb_strwidth') ? mb_strwidth($text, 'UTF-8') : strlen($text);
    }

    /** @return list<string> */
    public static function wrap(string $text, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (self::width($line . $character) > $width && $line !== '') {
                $lines[] = $line;
                $line = '';
            }
            $line .= $character;
        }

        return [...$lines, $line];
    }
}
