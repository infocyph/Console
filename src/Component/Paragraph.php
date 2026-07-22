<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class Paragraph implements Renderable
{
    public function __construct(private string $text, private string $role = 'text') {}

    public function frame(int $width = 80): Frame
    {
        $width = max(1, $width);
        $lines = [];
        foreach (preg_split('/\R/', $this->text) ?: [] as $paragraph) {
            foreach (TextWidth::wrap($paragraph, $width) as $line) {
                $lines[] = new Line($line, $this->role);
            }
        }

        return new Frame($lines);
    }
}
