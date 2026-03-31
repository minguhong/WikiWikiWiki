<?php

declare(strict_types=1);

function page_reset_derived_caches(): void
{
    page_all(reset: true);
    page_recent(reset: true);
    page_wanted(reset: true);
    page_orphaned(reset: true);
    page_redirects(reset: true);
    page_stub(reset: true);
    tag_all(reset: true);
    page_backlinks('', reset: true);
}

function page_indexes_load_bundle(): array
{
    return [
        'all' => page_index_load(),
        'search' => page_search_index_load(),
        'related' => page_related_index_load(),
    ];
}

function page_indexes_apply_title(array &$bundle, string $title, ?string $content = null): void
{
    $path = page_path($title);
    if (!is_file($path)) {
        unset($bundle['all'][$title], $bundle['search'][$title], $bundle['related'][$title]);
        return;
    }

    $modified = @filemtime($path);
    $sourceContent = $content ?? (page_get($title) ?? '');
    $bundle['all'][$title] = [
        'modified_at' => is_int($modified) ? $modified : 0,
        'redirect_target' => page_redirect_target_from_content($sourceContent),
    ];
    $bundle['search'][$title] = page_search_index_entry($title, $sourceContent);
    $bundle['related'][$title] = page_related_index_entry($title, $sourceContent);
}

function page_indexes_remove_title(array &$bundle, string $title): void
{
    unset($bundle['all'][$title], $bundle['search'][$title], $bundle['related'][$title]);
}

function page_indexes_save_bundle(array $bundle): void
{
    
    page_content_meta(reset: true);
    if (!page_index_save($bundle['all'])) {
        wiki_log('page.index_save_failed', ['path' => page_index_path()], 'error');
    }
    if (!page_search_index_save($bundle['search'])) {
        wiki_log('page.search_index_save_failed', ['path' => page_search_index_path()], 'error');
    }
    if (!page_related_index_save($bundle['related'])) {
        wiki_log('page.related_index_save_failed', ['path' => page_related_index_path()], 'error');
    }
    page_index_invalidate_cache();
    page_search_index_invalidate_cache();
    page_related_index_invalidate_cache();
}

function page_exists(string $title): bool
{
    return file_exists(page_path($title));
}

function create_default_page_for_title(
    string $title,
    ?string $historyAuthor = null,
    ?string $defaultPageSource = null,
): void {
    
    
    if (user_count() === 0) {
        return;
    }

    $sanitizedTitle = sanitize_page_title($title);
    if ($sanitizedTitle === '' || !page_title_fits_filename_limit($sanitizedTitle)) {
        return;
    }
    if (page_title_uses_reserved_route_suffix($sanitizedTitle)) {
        return;
    }

    $path = page_path($sanitizedTitle);
    $contentSource = $defaultPageSource ?? t('default_page');
    $content = page_normalize_content_for_save($contentSource);

    wiki_with_lock(function () use ($sanitizedTitle, $path, $content, $historyAuthor): void {
        if (is_file($path)) {
            return;
        }
        if (!file_put_atomic($path, $content)) {
            wiki_log('page.default_create_failed', ['title' => $sanitizedTitle, 'path' => $path], 'error');
            return;
        }
        page_history_seed_first_if_missing($sanitizedTitle, $historyAuthor);

        page_get($sanitizedTitle, invalidate: true);
        $bundle = page_indexes_load_bundle();
        page_indexes_apply_title($bundle, $sanitizedTitle, $content);
        page_indexes_save_bundle($bundle);
        page_reset_derived_caches();
    }, false, null);
}

function create_default_page(?string $historyAuthor = null): void
{
    create_default_page_for_title(HOME_PAGE, $historyAuthor);
}

function page_redirect_target(string $title): ?string
{
    $pageIndex = page_index_load();
    if (array_key_exists($title, $pageIndex)) {
        return page_index_entry_redirect_target($pageIndex[$title]);
    }

    
    $content = page_get($title);
    if ($content === null || $content === '') {
        return null;
    }

    return page_redirect_target_from_content($content);
}

function page_normalize_content_for_save(string $content): string
{
    $normalized = (string) preg_replace('/\r\n|\r/', "\n", $content);
    $normalized = trim($normalized);
    $normalized = format_markdown_content($normalized);
    $normalized = (string) preg_replace("/\n{3,}/", "\n\n", $normalized);
    if ($normalized !== '') {
        $normalized .= "\n";
    }
    return $normalized;
}

function page_deleted_marker_path(string $title): string
{
    $base = page_history_base($title);
    $tsSeed = time();
    do {
        $ts = date('YmdHis', $tsSeed);
        $sameTimestampFiles = glob(HISTORY_DIR . '/' . $base . '.' . $ts . '*.txt') ?: [];
        if ($sameTimestampFiles === []) {
            return HISTORY_DIR . '/' . $base . '.' . $ts . '._deleted.txt';
        }
        $tsSeed++;
    } while (true);
}

function page_get(string $title, ?bool $invalidate = null): ?string
{
    static $cache = [];
    $cacheKey = page_get_cache_key($title);
    if ($invalidate === true) {
        unset($cache[$cacheKey]);
    }
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    $path = page_path($title);
    if (!file_exists($path)) {
        $cache[$cacheKey] = null;
        return null;
    }
    $content = file_get_contents($path);
    $cache[$cacheKey] = $content === false ? null : $content;
    return $cache[$cacheKey];
}

function page_get_cache_key(string $title): string
{
    $filename = title_to_filename($title);
    return page_content_dir_is_case_insensitive()
        ? mb_strtolower($filename, 'UTF-8')
        : $filename;
}

function page_content_dir_is_case_insensitive(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (!is_dir(CONTENT_DIR) || !is_writable(CONTENT_DIR)) {
        $cache = false;
        return $cache;
    }

    try {
        $probeBase = '.caseprobe-' . bin2hex(random_bytes(6)) . '.tmp';
    } catch (Throwable) {
        $cache = false;
        return $cache;
    }

    $probePath = CONTENT_DIR . '/' . $probeBase;
    if (@file_put_contents($probePath, '1') === false) {
        $cache = false;
        return $cache;
    }

    $probeUpperPath = CONTENT_DIR . '/' . strtoupper($probeBase);
    $cache = file_exists($probeUpperPath);
    @unlink($probePath);
    return $cache;
}

function page_revision_token_from_state(string $title, string $content, ?int $modifiedAt): string
{
    $path = page_path($title);
    if (!is_file($path)) {
        return '';
    }

    $size = @filesize($path);
    if (!is_int($size)) {
        $size = strlen($content);
    }

    return (string) ($modifiedAt ?? 0) . ':' . (string) $size . ':' . sha1($content);
}

function page_revision_token(string $title): string
{
    if (!page_exists($title)) {
        return '';
    }

    $content = page_get($title, invalidate: true) ?? '';
    return page_revision_token_from_state($title, $content, page_last_modified_at($title));
}

function page_save_locked(string $title, string $normalizedContent): bool
{
    $path = page_path($title);

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        wiki_log('page.save.mkdir_failed', ['title' => $title, 'dir' => $dir], 'error');
        return false;
    }

    $needsSeed = is_file($path) && page_history($title, reset: true) === [];
    if ($needsSeed && !page_history_seed_first_if_missing($title)) {
        wiki_log('page.save_seed_history_failed', ['title' => $title], 'error');
        return false;
    }

    $result = file_put_atomic($path, $normalizedContent);
    if ($result) {
        $backupPath = page_backup_path($title);
        if (!@copy($path, $backupPath)) {
            wiki_log('page.save_backup_copy_failed', ['title' => $title, 'from' => $path, 'to' => $backupPath], 'warning');
        }
        page_history($title, reset: true);
        page_history_prune($title);
        page_get($title, invalidate: true);
        $bundle = page_indexes_load_bundle();
        page_indexes_apply_title($bundle, $title, $normalizedContent);
        page_indexes_save_bundle($bundle);
        page_reset_derived_caches();
    }
    return $result;
}

function page_save(string $title, string $content, bool $contentIsNormalized = false): bool
{
    $normalizedContent = $contentIsNormalized ? $content : page_normalize_content_for_save($content);
    return wiki_with_lock(fn() => page_save_locked($title, $normalizedContent), false, false);
}

function page_delete_locked(string $title, bool $deleteHistory = false): bool
{
    $path = page_path($title);
    if (!file_exists($path)) {
        if ($deleteHistory) {
            page_history_delete_all($title);
        }
        return true;
    }
    $deletedMarkerPath = page_deleted_marker_path($title);
    if (!@copy($path, $deletedMarkerPath)) {
        wiki_log('page.delete_marker_copy_failed', ['title' => $title, 'from' => $path, 'to' => $deletedMarkerPath], 'warning');
    }
    page_history_prune($title);
    $result = @unlink($path);
    if (!$result) {
        wiki_log('page.delete_unlink_failed', ['title' => $title, 'path' => $path], 'error');
    }
    if ($result) {
        if ($deleteHistory) {
            page_history_delete_all($title);
        }
        page_get($title, invalidate: true);
        $bundle = page_indexes_load_bundle();
        page_indexes_remove_title($bundle, $title);
        page_indexes_save_bundle($bundle);
        page_reset_derived_caches();
        page_history($title, reset: true);
    }
    return $result;
}

function page_delete(string $title, bool $deleteHistory = false): bool
{
    return wiki_with_lock(fn() => page_delete_locked($title, $deleteHistory), false, false);
}

function page_rename_locked(string $oldTitle, string $newTitle): bool
{
    $oldPath = page_path($oldTitle);
    $newPath = page_path($newTitle);

    if (!file_exists($oldPath)) {
        return false;
    }

    $needsSeed = page_history($oldTitle, reset: true) === [];
    if ($needsSeed && !page_history_seed_first_if_missing($oldTitle)) {
        wiki_log('page.rename_seed_history_failed', ['old_title' => $oldTitle, 'new_title' => $newTitle], 'error');
        return false;
    }

    if (file_exists($newPath)) {
        page_get($newTitle, invalidate: true);
        if (page_redirect_target($newTitle) !== null) {
            if (!@unlink($newPath) && file_exists($newPath)) {
                wiki_log('page.rename_unlink_redirect_failed', ['old_title' => $oldTitle, 'new_title' => $newTitle, 'path' => $newPath], 'warning');
                return false;
            }
        } else {
            return false;
        }
    }

    if (!@rename($oldPath, $newPath)) {
        wiki_log('page.rename_move_failed', ['old_title' => $oldTitle, 'new_title' => $newTitle, 'from' => $oldPath, 'to' => $newPath], 'error');
        return false;
    }
    if (!@touch($newPath)) {
        wiki_log('page.rename_touch_failed', ['old_title' => $oldTitle, 'new_title' => $newTitle, 'path' => $newPath], 'warning');
    }

    $oldBase = page_history_base($oldTitle);
    $newBase = page_history_base($newTitle);
    foreach (glob(HISTORY_DIR . '/' . $oldBase . '.*.txt') ?: [] as $file) {
        $newFile = HISTORY_DIR . '/' . $newBase . substr(basename($file), strlen($oldBase));
        if (!@rename($file, $newFile)) {
            wiki_log('page.rename_history_move_failed', ['old_title' => $oldTitle, 'new_title' => $newTitle, 'from' => $file, 'to' => $newFile], 'warning');
        }
    }

    $redirectStubContent = page_normalize_content_for_save("(redirect: $newTitle)");
    if (!file_put_atomic($oldPath, $redirectStubContent)) {
        wiki_log('page.rename_redirect_stub_write_failed', ['old_title' => $oldTitle, 'new_title' => $newTitle, 'path' => $oldPath], 'warning');
    }

    clearstatcache();

    $newContent = page_get($newTitle, invalidate: true) ?? '';
    $oldContent = page_get($oldTitle, invalidate: true) ?? '';
    $bundle = page_indexes_load_bundle();
    page_indexes_apply_title($bundle, $newTitle, $newContent);
    page_indexes_apply_title($bundle, $oldTitle, $oldContent);
    page_indexes_save_bundle($bundle);
    page_reset_derived_caches();
    page_history($oldTitle, reset: true);
    page_history($newTitle, reset: true);

    return true;
}

function page_rename(string $oldTitle, string $newTitle): bool
{
    return wiki_with_lock(fn() => page_rename_locked($oldTitle, $newTitle), false, false);
}

function page_update_if_current(
    string $title,
    string $content,
    string $newTitle = '',
    bool $deleteHistory = false,
    string $expectedRevision = '',
    bool $contentIsNormalized = false,
): array {
    $normalizedContent = $contentIsNormalized ? $content : page_normalize_content_for_save($content);
    $fallback = [
        'ok' => false,
        'conflict' => false,
        'saved' => false,
        'title' => $title,
        'current_content' => '',
        'current_modified_at' => null,
        'current_revision' => '',
    ];

    return wiki_with_lock(function () use (
        $title,
        $normalizedContent,
        $newTitle,
        $deleteHistory,
        $expectedRevision
    ): array {
        $currentExists = page_exists($title);
        $currentContent = $currentExists ? (page_get($title, invalidate: true) ?? '') : '';
        $currentModifiedAt = $currentExists ? page_last_modified_at($title) : null;
        $currentRevision = $currentExists ? page_revision_token_from_state($title, $currentContent, $currentModifiedAt) : '';

        $isStale = !hash_equals($expectedRevision, $currentRevision);

        if ($isStale && $normalizedContent !== $currentContent) {
            return [
                'ok' => false,
                'conflict' => true,
                'saved' => false,
                'title' => $title,
                'current_content' => $currentContent,
                'current_modified_at' => $currentModifiedAt,
                'current_revision' => $currentRevision,
            ];
        }

        if ($normalizedContent === '') {
            return [
                'ok' => page_delete_locked($title, $deleteHistory),
                'conflict' => false,
                'saved' => false,
                'title' => $title,
                'current_content' => '',
                'current_modified_at' => null,
                'current_revision' => '',
            ];
        }

        $resolvedTitle = $title;
        if ($newTitle !== '' && $newTitle !== $title && $title !== HOME_PAGE) {
            if (!page_rename_locked($title, $newTitle)) {
                return [
                    'ok' => false,
                    'conflict' => false,
                    'saved' => false,
                    'title' => $title,
                    'current_content' => $currentContent,
                    'current_modified_at' => $currentModifiedAt,
                    'current_revision' => $currentRevision,
                ];
            }
            $resolvedTitle = $newTitle;
        }

        $resolvedCurrentContent = page_get($resolvedTitle, invalidate: true) ?? '';
        $resolvedModifiedAt = page_last_modified_at($resolvedTitle);
        $resolvedRevision = page_revision_token_from_state($resolvedTitle, $resolvedCurrentContent, $resolvedModifiedAt);

        if ($normalizedContent === $resolvedCurrentContent) {
            return [
                'ok' => true,
                'conflict' => false,
                'saved' => false,
                'title' => $resolvedTitle,
                'current_content' => $resolvedCurrentContent,
                'current_modified_at' => $resolvedModifiedAt,
                'current_revision' => $resolvedRevision,
            ];
        }

        $saved = page_save_locked($resolvedTitle, $normalizedContent);
        $updatedContent = page_get($resolvedTitle, invalidate: true) ?? '';
        $updatedModifiedAt = page_last_modified_at($resolvedTitle);
        $updatedRevision = page_revision_token_from_state($resolvedTitle, $updatedContent, $updatedModifiedAt);

        return [
            'ok' => $saved,
            'conflict' => false,
            'saved' => $saved,
            'title' => $resolvedTitle,
            'current_content' => $updatedContent,
            'current_modified_at' => $updatedModifiedAt,
            'current_revision' => $updatedRevision,
        ];
    }, false, $fallback);
}

function page_last_modified_at(string $title): ?int
{
    $path = page_path($title);
    if (!file_exists($path)) {
        return null;
    }
    $modifiedAt = @filemtime($path);
    return is_int($modifiedAt) ? $modifiedAt : null;
}
