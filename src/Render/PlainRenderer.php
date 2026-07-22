<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

final class PlainRenderer implements Renderer
{
    public static function prefix(string $role): string
    {
        return match ($role) {
            'info' => '[INFO] ', 'note' => '[NOTE] ', 'success' => '[OK] ',
            'warning' => '[WARNING] ', 'error' => '[ERROR] ', default => '',
        };
    }

    public function render(Frame $frame): string
    {
        return implode('', array_map(
            static fn(Line $line): string => self::prefix($line->role) . self::ascii($line->text) . PHP_EOL,
            $frame->lines,
        ));
    }

    private static function ascii(string $text): string
    {
        return strtr($text, ['┌' => '+', '┐' => '+', '└' => '+', '┘' => '+', '├' => '+', '┤' => '+', '─' => '-', '│' => '|', '•' => '*', '✔' => '[OK]', '✘' => '[X]', '○' => 'o', '…' => '...']);
    }
}
