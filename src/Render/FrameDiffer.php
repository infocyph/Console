<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

/**
 * @internal
 */
final class FrameDiffer
{
    /** @return list<int> */
    public function changedLineIndexes(?Frame $previous, Frame $current): array
    {
        $changed = [];
        foreach ($current->lines as $index => $line) {
            $before = $previous === null ? null : ($previous->lines[$index] ?? null);
            if ($before === null || $before->text !== $line->text || $before->role !== $line->role) {
                $changed[] = $index;
            }
        }

        return $changed;
    }

    public function staleLineCount(?Frame $previous, Frame $current): int
    {
        return max(0, ($previous === null ? 0 : count($previous->lines)) - count($current->lines));
    }
}
