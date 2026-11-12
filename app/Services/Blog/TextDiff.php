<?php

namespace App\Services\Blog;

/**
 * Small line-based diff (longest common subsequence) shaped for a side-by-side view.
 */
class TextDiff
{
    /**
     * Inputs larger than this are compared without alignment to keep memory bounded.
     */
    private const MAX_LINES = 1500;

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{type: 'same'|'changed'|'removed'|'added', left: string|null, right: string|null}>
     */
    public function sideBySide(array $old, array $new): array
    {
        $operations = count($old) > self::MAX_LINES || count($new) > self::MAX_LINES
            ? [...array_map(fn (string $line): array => ['-', $line], $old), ...array_map(fn (string $line): array => ['+', $line], $new)]
            : $this->operations($old, $new);

        $rows = [];
        $removed = [];
        $added = [];

        $flush = function () use (&$rows, &$removed, &$added): void {
            $count = max(count($removed), count($added));

            for ($index = 0; $index < $count; $index++) {
                $left = $removed[$index] ?? null;
                $right = $added[$index] ?? null;
                $rows[] = [
                    'type' => $left !== null && $right !== null ? 'changed' : ($left !== null ? 'removed' : 'added'),
                    'left' => $left,
                    'right' => $right,
                ];
            }

            $removed = [];
            $added = [];
        };

        foreach ($operations as [$operation, $line]) {
            if ($operation === '=') {
                $flush();
                $rows[] = ['type' => 'same', 'left' => $line, 'right' => $line];
            } elseif ($operation === '-') {
                $removed[] = $line;
            } else {
                $added[] = $line;
            }
        }

        $flush();

        return $rows;
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{0: '='|'-'|'+', 1: string}>
     */
    private function operations(array $old, array $new): array
    {
        $oldCount = count($old);
        $newCount = count($new);
        $lengths = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($i = $oldCount - 1; $i >= 0; $i--) {
            for ($j = $newCount - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $old[$i] === $new[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;

        while ($i < $oldCount && $j < $newCount) {
            if ($old[$i] === $new[$j]) {
                $operations[] = ['=', $old[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $operations[] = ['-', $old[$i++]];
            } else {
                $operations[] = ['+', $new[$j++]];
            }
        }

        while ($i < $oldCount) {
            $operations[] = ['-', $old[$i++]];
        }

        while ($j < $newCount) {
            $operations[] = ['+', $new[$j++]];
        }

        return $operations;
    }
}
