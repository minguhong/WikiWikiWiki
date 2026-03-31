<?php

declare(strict_types=1);

function normalize_host_with_port(string $value): string
{
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $raw) === 1) {
        $parsedHost = parse_url($raw, PHP_URL_HOST);
        if (!is_string($parsedHost) || $parsedHost === '') {
            return '';
        }
        $parsedPort = parse_url($raw, PHP_URL_PORT);
        $raw = $parsedHost . (is_int($parsedPort) ? ':' . $parsedPort : '');
    }
    if (preg_match('/[\/?#\s,]/', $raw) === 1) {
        return '';
    }

    $host = '';
    $port = null;

    if (str_starts_with($raw, '[')) {
        if (!preg_match('/^\[([a-f0-9:]+)\](?::(\d{1,5}))?$/i', $raw, $m)) {
            return '';
        }
        $host = '[' . strtolower($m[1]) . ']';
        $port = isset($m[2]) ? (int) $m[2] : null;
    } else {
        if (!preg_match('/^([a-z0-9.-]+)(?::(\d{1,5}))?$/i', $raw, $m)) {
            return '';
        }
        $host = strtolower($m[1]);
        $host = rtrim($host, '.');
        if ($host === '' || str_contains($host, '..')) {
            return '';
        }
        $port = isset($m[2]) ? (int) $m[2] : null;
    }

    if (!preg_match('/^(?:[a-z0-9.-]+|\[[a-f0-9:]+\])$/i', $host)) {
        return '';
    }
    if ($port !== null && ($port < 1 || $port > 65535)) {
        return '';
    }

    return strtolower($host) . ($port !== null ? ':' . $port : '');
}

function password_hash_bcrypt_safe(#[\SensitiveParameter] string $password): ?string
{
    try {
        return password_hash($password, PASSWORD_BCRYPT);
    } catch (Throwable $error) {
        wiki_log('auth.password_hash_exception', ['error' => $error->getMessage()], 'error');
        return null;
    }
}
