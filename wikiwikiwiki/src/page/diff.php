<?php

declare(strict_types=1);

const DIFF_LINE_LIMIT = 500;
const DIFF_CONTEXT_LINES = 2;

function diff_lines(string $old, string $new): array
{
    $oldLines = $old !== '' ? explode("\n", rtrim($old, "\n")) : [];
    $newLines = $new !== '' ? explode("\n", rtrim($new, "\n")) : [];
    $m = count($oldLines);
    $n = count($newLines);

    if ($m > DIFF_LINE_LIMIT || $n > DIFF_LINE_LIMIT) {
        return [['type' => 'too_large', 'line' => '']];
    }

    $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    for ($i = 1; $i <= $m; $i++) {
        for ($j = 1; $j <= $n; $j++) {
            $dp[$i][$j] = $oldLines[$i - 1] === $newLines[$j - 1]
                ? $dp[$i - 1][$j - 1] + 1
                : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
        }
    }

    $ops = [];
    $i = $m;
    $j = $n;
    while ($i > 0 || $j > 0) {
        if ($i > 0 && $j > 0 && $oldLines[$i - 1] === $newLines[$j - 1]) {
            $ops[] = ['type' => 'context', 'line' => $oldLines[$i - 1]];
            $i--;
            $j--;
        } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
            $ops[] = ['type' => 'add', 'line' => $newLines[$j - 1]];
            $j--;
        } else {
            $ops[] = ['type' => 'remove', 'line' => $oldLines[$i - 1]];
            $i--;
        }
    }

    $ops = array_reverse($ops);
    $changeIndexes = [];
    foreach ($ops as $index => $entry) {
        if ($entry['type'] !== 'context') {
            $changeIndexes[] = $index;
        }
    }

    if ($changeIndexes === []) {
        return [];
    }

    $keep = array_fill(0, count($ops), false);
    foreach ($changeIndexes as $changeIndex) {
        $start = max(0, $changeIndex - DIFF_CONTEXT_LINES);
        $end = min(count($ops) - 1, $changeIndex + DIFF_CONTEXT_LINES);
        for ($index = $start; $index <= $end; $index++) {
            $keep[$index] = true;
        }
    }

    $diff = [];
    foreach ($ops as $index => $entry) {
        if (
            $keep[$index]
            && !($entry['type'] === 'context' && $entry['line'] === '')
        ) {
            $diff[] = $entry;
        }
    }

    return $diff;
}
