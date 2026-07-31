<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

use Infocyph\Console\Style\Color;
use Infocyph\Console\Style\DefaultTheme;
use Infocyph\Console\Style\Theme;
use Infocyph\Console\Terminal\ColorDepth;

final readonly class AnsiRenderer implements Renderer
{
    private Theme $theme;

    public function __construct(
        ?Theme $theme = null,
        private ColorDepth $colorDepth = ColorDepth::BASIC,
    ) {
        $this->theme = $theme ?? new DefaultTheme();
    }

    public function render(Frame $frame): string
    {
        $output = '';
        foreach ($frame->lines as $line) {
            $text = PlainRenderer::prefix($line->role) . $line->text;
            $codes = $this->codes($line);
            $output .= $codes === []
                ? $text . PHP_EOL
                : "\033[" . implode(';', $codes) . 'm' . $text . "\033[0m" . PHP_EOL;
        }

        return $output;
    }

    private function basicColor(Color $color, bool $background): int
    {
        $code = match ($color) {
            Color::BLACK => 30,
            Color::RED => 31,
            Color::GREEN => 32,
            Color::YELLOW => 33,
            Color::BLUE => 34,
            Color::MAGENTA => 35,
            Color::CYAN => 36,
            Color::WHITE => 37,
            Color::BRIGHT_BLACK => 90,
            Color::BRIGHT_RED => 91,
            Color::BRIGHT_GREEN => 92,
            Color::BRIGHT_YELLOW => 93,
            Color::BRIGHT_BLUE => 94,
            Color::BRIGHT_MAGENTA => 95,
            Color::BRIGHT_CYAN => 96,
            Color::BRIGHT_WHITE => 97,
            Color::DEFAULT => 39,
        };

        return $background ? $code + 10 : $code;
    }

    /** @return list<string> */
    private function codes(Line $line): array
    {
        if ($this->colorDepth === ColorDepth::NONE) {
            return [];
        }

        $style = $this->theme->style($line->role);
        $codes = [];
        if ($style->bold) {
            $codes[] = '1';
        }
        if ($style->dim) {
            $codes[] = '2';
        }
        if ($style->italic) {
            $codes[] = '3';
        }
        if ($style->underline) {
            $codes[] = '4';
        }

        $foreground = $this->color($style->foreground, false);
        if ($foreground !== null) {
            $codes[] = $foreground;
        }
        $background = $this->color($style->background, true);
        if ($background !== null) {
            $codes[] = $background;
        }

        return $codes;
    }

    private function color(Color $color, bool $background): ?string
    {
        if ($color === Color::DEFAULT || $this->colorDepth === ColorDepth::NONE) {
            return null;
        }

        return match ($this->colorDepth) {
            ColorDepth::BASIC => (string) $this->basicColor($color, $background),
            ColorDepth::ANSI_256 => ($background ? '48' : '38') . ';5;' . $this->indexedColor($color),
            ColorDepth::TRUE_COLOR => ($background ? '48' : '38') . ';2;' . implode(';', $this->rgbColor($color)),
        };
    }

    private function indexedColor(Color $color): int
    {
        return match ($color) {
            Color::BLACK => 0,
            Color::RED => 1,
            Color::GREEN => 2,
            Color::YELLOW => 3,
            Color::BLUE => 4,
            Color::MAGENTA => 5,
            Color::CYAN => 6,
            Color::WHITE => 7,
            Color::BRIGHT_BLACK => 8,
            Color::BRIGHT_RED => 9,
            Color::BRIGHT_GREEN => 10,
            Color::BRIGHT_YELLOW => 11,
            Color::BRIGHT_BLUE => 12,
            Color::BRIGHT_MAGENTA => 13,
            Color::BRIGHT_CYAN => 14,
            Color::BRIGHT_WHITE => 15,
            Color::DEFAULT => 7,
        };
    }

    /** @return array{int, int, int} */
    private function rgbColor(Color $color): array
    {
        return match ($color) {
            Color::BLACK => [15, 23, 42],
            Color::RED => [220, 38, 38],
            Color::GREEN => [22, 163, 74],
            Color::YELLOW => [202, 138, 4],
            Color::BLUE => [37, 99, 235],
            Color::MAGENTA => [147, 51, 234],
            Color::CYAN => [8, 145, 178],
            Color::WHITE => [203, 213, 225],
            Color::BRIGHT_BLACK => [100, 116, 139],
            Color::BRIGHT_RED => [248, 113, 113],
            Color::BRIGHT_GREEN => [74, 222, 128],
            Color::BRIGHT_YELLOW => [250, 204, 21],
            Color::BRIGHT_BLUE => [96, 165, 250],
            Color::BRIGHT_MAGENTA => [216, 180, 254],
            Color::BRIGHT_CYAN => [34, 211, 238],
            Color::BRIGHT_WHITE => [248, 250, 252],
            Color::DEFAULT => [203, 213, 225],
        };
    }
}
