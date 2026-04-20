<?php

declare(strict_types=1);

function edit_view_data(
    string $title,
    ?string $content,
    bool $isNew,
    ?int $modifiedAt,
    string $revision = '',
    bool $conflict = false,
    ?string $currentContent = null,
    string $existingNoticeKey = '',
    string $newTitleInput = '',
): array {
    $data = [
        'page' => $title,
        'content' => $content,
        'isNew' => $isNew,
        'modifiedAt' => $modifiedAt,
        'revision' => $revision,
    ];

    if ($conflict) {
        if ($existingNoticeKey === '') {
            $existingNoticeKey = 'message.edit.conflict_notice';
        }
        $data['conflict'] = true;
        $data['currentContent'] = $currentContent;
        $data['existingNoticeKey'] = $existingNoticeKey;
    }

    if ($newTitleInput !== '') {
        $data['newTitleInput'] = $newTitleInput;
    }

    return $data;
}

function handle_home(string $method, array $matches): void
{
    require_get_method($method);
    render_view(HOME_PAGE);
}

function handle_new_page(string $method, array $matches): void
{
    require_edit_access();

    if ($method === 'POST') {
        validate_post_request();

        $rawTitle = request_trimmed($_POST, 'title');
        $title = sanitize_page_title($rawTitle);
        $content = request_string($_POST, 'content');

        if (mb_strlen($rawTitle) > PAGE_TITLE_MAX_LENGTH) {
            flash('error', t('flash.page.title_too_long_message'));
            render('new', ['inputTitle' => $rawTitle, 'inputContent' => $content]);
            exit;
        }

        if ($title === '') {
            flash('error', t('flash.page.title_required'));
            render('new', ['inputTitle' => $rawTitle, 'inputContent' => $content]);
            exit;
        }

        if (page_title_uses_reserved_route_suffix($title)) {
            flash('error', t('flash.page.title_reserved'));
            render('new', ['inputTitle' => $rawTitle, 'inputContent' => $content]);
            exit;
        }

        if (!page_title_fits_filename_limit($title)) {
            flash('error', t('flash.page.title_too_long_filename'));
            render('new', ['inputTitle' => $rawTitle, 'inputContent' => $content]);
            exit;
        }

        if (page_exists($title)) {
            if ($title === HOME_PAGE) {
                flash('error', t('flash.page.title_home_page_reserved'));
                render('new', ['inputTitle' => $rawTitle, 'inputContent' => $content]);
                exit;
            }
            flash('error', t('flash.page.exists'));
            $currentContent = page_get($title) ?? '';
            $currentModifiedAt = page_last_modified_at($title);
            render('edit', edit_view_data(
                title: $title,
                content: $content,
                isNew: false,
                modifiedAt: $currentModifiedAt,
                revision: page_revision_token_from_state($title, $currentContent, $currentModifiedAt),
                conflict: true,
                currentContent: $currentContent,
                existingNoticeKey: 'message.page.overwrite_warning',
            ));
            exit;
        }

        if (trim($content) === '') {
            flash('error', t('flash.page.content_required'));
            render('new', ['inputTitle' => $title]);
            exit;
        }

        if (!page_save($title, $content)) {
            flash('error', t('flash.page.save_failed'));
            redirect(url('/new'));
        }
        flash('success', t('flash.page.created'));
        redirect(url($title));
    }

    render('new');
}

function handle_random(string $method, array $matches): void
{
    require_get_method($method);
    $pages = page_random(1);
    if ($pages === []) {
        redirect(url('/'));
    }
    redirect(url((string) ($pages[0]['title'] ?? '')));
}

function handle_wiki_edit(string $method, array $matches): void
{
    require_get_method($method);
    $title = route_title_or_400($matches);

    require_edit_access();

    $content = page_get($title);
    $isNew = ($content === null);
    $modifiedAt = page_last_modified_at($title);
    $revision = ($content === null || $modifiedAt === null)
        ? ''
        : page_revision_token_from_state($title, $content, $modifiedAt);
    render('edit', edit_view_data(
        title: $title,
        content: $content,
        isNew: $isNew,
        modifiedAt: $modifiedAt,
        revision: $revision,
    ));
}

function handle_wiki_page(string $method, array $matches): void
{
    if ($method === 'GET') {
        handle_wiki_view($method, $matches);
        return;
    }

    if ($method === 'POST') {
        handle_wiki_update($method, $matches);
        return;
    }

    header('Allow: GET, POST');
    render_error_page(405, '405', t('error.request.method_not_allowed'));
}

function handle_wiki_view(string $method, array $matches): void
{
    $title = route_title_or_400($matches);
    render_view($title);
}

function handle_wiki_update(string $method, array $matches): void
{
    if ($method !== 'POST') {
        header('Allow: POST');
        render_error_page(405, '405', t('error.request.method_not_allowed'));
    }

    $title = route_title_or_400($matches);
    require_edit_access();
    validate_post_request();

    if (!array_key_exists('original_revision', $_POST)) {
        flash('error', t('flash.page.save_failed'));
        redirect(url($title, '/edit'));
    }

    $content = request_string($_POST, 'content');
    $normalizedContent = page_normalize_content_for_save($content);
    $originalRevision = request_string($_POST, 'original_revision');
    $deleteHistoryRequested = request_string($_POST, 'delete_history', '0') === '1';
    $deleteHistory = $deleteHistoryRequested && is_admin();

    $rawNewTitle = request_trimmed($_POST, 'new_title');
    $newTitle = '';
    if ($rawNewTitle !== '' && $rawNewTitle !== $title && $title !== HOME_PAGE) {
        $renderEditError = static function (
            string $messageKey,
            string $newTitleInput = '',
            bool $conflict = false,
            ?string $currentContent = null,
            ?int $currentModifiedAt = null,
            string $existingNoticeKey = '',
        ) use ($title, $content): never {
            flash('error', t($messageKey));
            $sourceContent = page_get($title) ?? '';
            $sourceModifiedAt = page_last_modified_at($title);
            render('edit', edit_view_data(
                title: $title,
                content: $content,
                isNew: false,
                modifiedAt: $conflict ? $currentModifiedAt : $sourceModifiedAt,
                revision: page_revision_token_from_state($title, $sourceContent, $sourceModifiedAt),
                conflict: $conflict,
                currentContent: $conflict ? $currentContent : null,
                existingNoticeKey: $existingNoticeKey,
                newTitleInput: $newTitleInput,
            ));
            exit;
        };

        if (mb_strlen($rawNewTitle) > PAGE_TITLE_MAX_LENGTH) {
            $renderEditError('flash.page.title_too_long_message', $rawNewTitle);
        }
        $newTitle = sanitize_page_title($rawNewTitle);
        if ($newTitle === '') {
            $renderEditError('flash.page.title_required', $rawNewTitle);
        }
        if ($newTitle === HOME_PAGE) {
            $renderEditError('flash.page.title_home_page_reserved', $rawNewTitle);
        }
        if (page_title_uses_reserved_route_suffix($newTitle)) {
            $renderEditError('flash.page.title_reserved', $rawNewTitle);
        }
        if (!page_title_fits_filename_limit($newTitle)) {
            $renderEditError('flash.page.title_too_long_filename', $rawNewTitle);
        }
        if (page_exists($newTitle) && !page_titles_resolve_same_existing_file($title, $newTitle)) {
            if (page_redirect_target($newTitle) === null) {
                $renderEditError(
                    messageKey: 'flash.page.exists',
                    newTitleInput: $rawNewTitle,
                    conflict: true,
                    currentContent: page_get($newTitle) ?? '',
                    currentModifiedAt: page_last_modified_at($newTitle),
                    existingNoticeKey: 'message.edit.rename_target_exists',
                );
            }
        }
    }

    $result = page_update_if_current(
        title: $title,
        content: $normalizedContent,
        newTitle: $newTitle,
        deleteHistory: $deleteHistory,
        expectedRevision: $originalRevision,
        contentIsNormalized: true,
    );

    if (($result['conflict'] ?? false) === true) {
        render('edit', edit_view_data(
            title: $title,
            content: $content,
            isNew: false,
            modifiedAt: $result['current_modified_at'] ?? null,
            revision: (string) ($result['current_revision'] ?? ''),
            conflict: true,
            currentContent: (string) ($result['current_content'] ?? ''),
        ));
        exit;
    }

    if (($result['ok'] ?? false) !== true) {
        flash('error', t('flash.page.save_failed'));
        if ($normalizedContent === '') {
            redirect(url('/'));
        }
        redirect(url($title, '/edit'));
    }

    $resolvedTitle = (string) ($result['title'] ?? $title);
    if ($normalizedContent === '') {
        flash('success', t('flash.page.deleted'));
        redirect(url('/'));
    }
    if (($result['saved'] ?? false) !== true) {
        redirect(url($resolvedTitle));
    }
    flash('success', t('flash.page.saved'));
    redirect(url($resolvedTitle));
}


function handle_wiki_backlinks(string $method, array $matches): void
{
    require_get_method($method);
    $title = route_title_or_400($matches);

    $backlinks = page_backlinks($title);
    render('backlinks', [
        'page' => $title,
        'backlinks' => $backlinks,
        'parent' => page_parent($title),
        'children' => page_children($title),
        'siblings' => page_siblings($title),
    ]);
}

function wiki_raw_content_type(string $ext): string
{
    return strtolower($ext) === 'md'
        ? 'text/markdown; charset=utf-8'
        : 'text/plain; charset=utf-8';
}

function handle_wiki_raw(string $method, array $matches): void
{
    require_get_method($method);
    $title = route_title_or_400($matches);
    $ext = strtolower(request_string($matches, 2, 'txt'));

    $content = page_get($title);
    if ($content === null) {
        render_404_page($title . '.' . $ext);
    }
    $filename = pathinfo(title_to_filename($title), PATHINFO_FILENAME) . '.' . ($ext === 'md' ? 'md' : 'txt');
    $safeFilename = str_replace(["\r", "\n", '"'], '', $filename);
    header('Content-Type: ' . wiki_raw_content_type($ext));
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    echo $content;
}
