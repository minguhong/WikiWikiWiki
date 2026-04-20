<?php

declare(strict_types=1);

function user_normalize_username(string $username): string
{
    return strtolower(trim($username));
}

function user_normalize_record(string $username, array $data): array
{
    $normalizedUsername = user_normalize_username((string) ($data['username'] ?? $username));
    $role = (string) ($data['role'] ?? 'editor');
    if ($role !== 'admin' && $role !== 'editor') {
        $role = 'editor';
    }

    $createdAt = $data['created_at'] ?? 0;
    if (!is_int($createdAt)) {
        $createdAt = is_numeric($createdAt) ? (int) $createdAt : 0;
    }
    if ($createdAt < 0) {
        $createdAt = 0;
    }

    return [
        'username' => $normalizedUsername,
        'password_hash' => (string) ($data['password_hash'] ?? ''),
        'role' => $role,
        'created_at' => $createdAt,
    ];
}

function user_path(string $username): string
{
    $safe = (string) preg_replace('/[^a-z0-9]/', '_', user_normalize_username($username));
    return USERS_DIR . '/' . $safe . '.json';
}

function user_get(string $username): ?array
{
    $path = user_path($username);
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        wiki_log('user.read_failed', ['path' => $path], 'warning');
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? user_normalize_record($username, $data) : null;
}

function user_encode_json(array $data): ?string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        wiki_log('user.json_encode_failed', [], 'error');
        return null;
    }
    return $json;
}

function user_create(string $username, array $data): bool
{
    $username = user_normalize_username($username);
    $data = user_normalize_record($username, $data);
    $json = user_encode_json($data);
    if ($json === null) {
        return false;
    }

    return wiki_with_lock(function () use ($username, $json): bool {
        $path = user_path($username);

        if (is_file($path)) {
            wiki_log('user.create_open_failed', ['path' => $path], 'warning');
            return false;
        }

        if (!file_put_atomic($path, $json)) {
            wiki_log('user.create_write_failed', ['path' => $path], 'error');
            return false;
        }

        user_all(reset: true);
        return true;
    }, false, false);
}

function user_update(string $username, array $data): bool
{
    $username = user_normalize_username($username);
    $data = user_normalize_record($username, $data);
    $json = user_encode_json($data);
    if ($json === null) {
        return false;
    }

    return wiki_with_lock(function () use ($username, $json): bool {
        $path = user_path($username);
        $result = file_put_atomic($path, $json);
        if ($result) {
            user_all(reset: true);
        }
        return $result;
    }, false, false);
}

function user_delete_checked(string $username): array
{
    $username = user_normalize_username($username);

    return wiki_with_lock(function () use ($username): array {
        $path = user_path($username);
        if (!is_file($path)) {
            return ['ok' => true, 'error' => ''];
        }

        $target = user_get($username);
        if (is_array($target) && (($target['role'] ?? '') === 'admin')) {
            $adminCount = 0;
            foreach (user_all(reset: true) as $row) {
                if (($row['role'] ?? '') === 'admin') {
                    $adminCount++;
                }
            }
            if ($adminCount <= 1) {
                return ['ok' => false, 'error' => 'last_admin'];
            }
        }

        $result = @unlink($path);
        if (!$result) {
            wiki_log('user.delete_failed', ['path' => $path], 'error');
            return ['ok' => false, 'error' => 'delete_failed'];
        }

        user_all(reset: true);
        return ['ok' => true, 'error' => ''];
    }, false, ['ok' => false, 'error' => 'delete_failed']);
}

function user_delete(string $username): bool
{
    return (bool) (user_delete_checked($username)['ok'] ?? false);
}

function user_all(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $files = glob(USERS_DIR . '/*.json') ?: [];
    $users = [];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            wiki_log('user.read_failed', ['path' => $file], 'warning');
            continue;
        }
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $users[] = user_normalize_record(
                pathinfo($file, PATHINFO_FILENAME),
                $data,
            );
        }
    }
    usort($users, fn($a, $b) => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0));
    $cache = $users;
    return $cache;
}

function user_count(): int
{
    return count(user_all());
}

function migrate_to_multi_user(): void
{
    if (user_count() > 0) {
        return;
    }
    if (!defined('PASSWORD_HASH') || PASSWORD_HASH === '') {
        return;
    }
    user_create('admin', [
        'username' => 'admin',
        'password_hash' => PASSWORD_HASH,
        'role' => 'admin',
        'created_at' => time(),
    ]);
}
