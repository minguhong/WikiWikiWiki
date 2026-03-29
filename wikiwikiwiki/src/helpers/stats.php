<?php

declare(strict_types=1);

function dir_size(string $dir): int
{
    $size = 0;
    if (!is_dir($dir)) {
        return 0;
    }
    $files = scandir($dir);
    if ($files === false) {
        return 0;
    }
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        $fileSize = is_file($path) ? @filesize($path) : false;
        $size += is_int($fileSize) ? $fileSize : 0;
    }
    return $size;
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}
