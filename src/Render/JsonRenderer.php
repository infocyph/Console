<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

final class JsonRenderer implements Renderer
{
    public function render(Frame $frame): string
    {
        return implode('', array_map(
            static fn(Line $line): string => json_encode(['type' => $line->role, 'message' => $line->text], JSON_THROW_ON_ERROR) . PHP_EOL,
            $frame->lines,
        ));
    }
}
