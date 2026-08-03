<?php

declare(strict_types=1);

namespace Infocyph\Console\Style;

final readonly class DefaultTheme implements Theme
{
    /** @var array<string, Style> */
    private array $styles;

    public function __construct()
    {
        $this->styles = [
            'text' => new Style(),
            'success' => new Style(Color::BRIGHT_GREEN, bold: true),
            'warning' => new Style(Color::BRIGHT_YELLOW, bold: true),
            'error' => new Style(Color::BRIGHT_RED, bold: true),
            'danger' => new Style(Color::BRIGHT_WHITE, bold: true, background: Color::RED),
            'info' => new Style(Color::BRIGHT_BLUE),
            'accent' => new Style(Color::BRIGHT_CYAN, bold: true),
            'note' => new Style(Color::BRIGHT_MAGENTA),
            'title' => new Style(Color::BRIGHT_CYAN, bold: true),
            'section' => new Style(Color::BRIGHT_BLUE, bold: true),
            'heading' => new Style(Color::BRIGHT_WHITE, bold: true),
            'primary' => new Style(Color::BRIGHT_CYAN, bold: true),
            'selected' => new Style(Color::BLACK, bold: true, background: Color::BRIGHT_CYAN),
            'progress' => new Style(Color::BRIGHT_GREEN),
            'definition' => new Style(Color::CYAN),
            'definition-label' => new Style(Color::BRIGHT_CYAN, bold: true),
            'definition-separator' => new Style(Color::BRIGHT_BLACK, dim: true),
            'definition-value' => new Style(Color::WHITE),
            'command' => new Style(Color::BRIGHT_CYAN, bold: true),
            'option' => new Style(Color::BRIGHT_GREEN),
            'muted' => new Style(Color::BRIGHT_BLACK, dim: true),
            'disabled' => new Style(Color::BRIGHT_BLACK, dim: true),
            'border' => new Style(Color::BRIGHT_BLACK, dim: true),
        ];
    }

    public function style(string $role): Style
    {
        return $this->styles[$role] ?? $this->styles['text'];
    }
}
