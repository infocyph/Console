<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

use Infocyph\Console\Style\Color;
use Infocyph\Console\Style\DefaultTheme;
use Infocyph\Console\Style\Theme;

final readonly class AnsiRenderer implements Renderer
{
    public function __construct(private ?Theme $theme = null) {}

    public function render(Frame $frame): string
    {
        return implode('', array_map(function (Line $line): string {
            $prefix = PlainRenderer::prefix($line->role);
            $style = ($this->theme ?? new DefaultTheme())->style($line->role);
            $codes = [];
            if ($style->bold) {
                $codes[] = '1';
            }
            if ($style->dim) {
                $codes[] = '2';
            }
            $color = match ($style->foreground) {
                Color::DEFAULT => null, Color::RED => '31', Color::GREEN => '32', Color::YELLOW => '33', Color::BLUE => '34', Color::MAGENTA => '35', Color::CYAN => '36', Color::WHITE => '37',
            };
            if ($color !== null) {
                $codes[] = $color;
            }
            $code = $codes === [] ? null : implode(';', $codes);

            return $code === null
                ? $prefix . $line->text . PHP_EOL
                : "\033[{$code}m{$prefix}{$line->text}\033[0m" . PHP_EOL;
        }, $frame->lines));
    }
}
