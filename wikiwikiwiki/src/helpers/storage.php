<?php

declare(strict_types=1);

function wiki_lock_path(): string
{
    return BASE_DIR . '/.lock';
}

function wiki_with_lock(callable $callback, bool $shared = false, mixed $fallback = null): mixed
{
    $fh = @fopen(wiki_lock_path(), 'c+');
    if ($fh === false) {
        wiki_log('lock.open_failed', ['path' => wiki_lock_path()], 'error');
        return $fallback;
    }
    $mode = $shared ? LOCK_SH : LOCK_EX;
    if (!@flock($fh, $mode)) {
        @fclose($fh);
        wiki_log('lock.acquire_failed', ['shared' => $shared], 'error');
        return $fallback;
    }
    try {
        return $callback();
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}

function file_put_atomic(string $path, string $content): bool
{
    $dir = dirname($path);
    $tmp = $dir . '/.tmp.' . bin2hex(random_bytes(4));

    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        wiki_log('file.atomic_write_failed', ['path' => $path, 'tmp' => $tmp], 'error');
        return false;
    }

    if (!@rename($tmp, $path)) {
        wiki_log('file.atomic_rename_failed', ['path' => $path, 'tmp' => $tmp], 'error');
        @unlink($tmp);
        return false;
    }

    return true;
}

function ensure_directories(): void
{
    foreach ([CONTENT_DIR, HISTORY_DIR, USERS_DIR, CACHE_DIR] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            wiki_log('filesystem.mkdir_failed', ['dir' => $dir], 'error');
        }
    }
}

function storage_boot_report(): array
{
    $dirs = [CONTENT_DIR, HISTORY_DIR, USERS_DIR, CACHE_DIR];
    $missingDirs = [];
    $notWritableDirs = [];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            $missingDirs[] = $dir;
            continue;
        }
        if (!is_writable($dir)) {
            $notWritableDirs[] = $dir;
        }
    }

    $lockOk = wiki_with_lock(fn() => true, false, false) === true;

    return [
        'ok' => ($missingDirs === [] && $notWritableDirs === [] && $lockOk),
        'missing_dirs' => $missingDirs,
        'not_writable_dirs' => $notWritableDirs,
        'lock_ok' => $lockOk,
    ];
}

function fail_fast_on_storage_unavailable(): void
{
    $report = storage_boot_report();
    if (($report['ok'] ?? false) === true) {
        return;
    }

    wiki_log('bootstrap.storage_unavailable', $report, 'error');

    $missing = (array) ($report['missing_dirs'] ?? []);
    $notWritable = (array) ($report['not_writable_dirs'] ?? []);
    $lockOk = (bool) ($report['lock_ok'] ?? false);

    $lines = [];
    if ($missing !== []) {
        $lines[] = 'Missing directories: ' . implode(', ', $missing);
    }
    if ($notWritable !== []) {
        $lines[] = 'Not writable: ' . implode(', ', $notWritable);
    }
    if (!$lockOk) {
        $lines[] = 'Lock check failed: ' . wiki_lock_path();
    }
    if ($lines === []) {
        $lines[] = 'Unknown storage bootstrap error.';
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "[503] Storage is not writable.\n");
        foreach ($lines as $line) {
            fwrite(STDERR, '- ' . $line . "\n");
        }
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>503 Service Unavailable</title></head><body><h1>503 Service Unavailable</h1><p>Storage is not ready for write operations. Check file permissions for content, history, users, cache, and lock file.</p><ul>';
    foreach ($lines as $line) {
        echo '<li>' . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</li>';
    }
    echo '</ul></body></html>';
    exit;
}
