<?php

declare(strict_types=1);

namespace Infocyph\Console\Style;

final class DefaultTheme implements Theme
{
    public function style(string $role): Style
    {
        return match ($role) {
            'success' => new Style(Color::GREEN), 'warning' => new Style(Color::YELLOW), 'error' => new Style(Color::RED),
            'danger' => new Style(Color::RED), 'info', 'accent' => new Style(Color::CYAN), 'note' => new Style(Color::MAGENTA),
            'title', 'section', 'heading', 'primary' => new Style(Color::WHITE, true), 'selected' => new Style(Color::BLUE, true),
            'muted', 'disabled', 'border' => new Style(Color::DEFAULT, dim: true), default => new Style(),
        };
    }
}
