<?php

declare(strict_types=1);

function export_zip_add_txt_files(ZipArchive $zip, string $sourceDir, string $zipDir): bool
{
    $files = scandir($sourceDir);
    if ($files === false) {
        return false;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !str_ends_with((string) $file, '.txt')) {
            continue;
        }

        $sourcePath = $sourceDir . '/' . $file;
        if (!is_file($sourcePath)) {
            continue;
        }

        if (!$zip->addFile($sourcePath, $zipDir . '/' . $file)) {
            return false;
        }
    }

    return true;
}

function export_zip_unlocked(): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'wiki_');
    if ($tmpFile === false) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpFile);
        return null;
    }

    if (
        !$zip->addEmptyDir('content')
        || !$zip->addEmptyDir('history')
        || !export_zip_add_txt_files($zip, CONTENT_DIR, 'content')
        || !export_zip_add_txt_files($zip, HISTORY_DIR, 'history')
    ) {
        $zip->close();
        @unlink($tmpFile);
        return null;
    }

    if (!$zip->close()) {
        @unlink($tmpFile);
        return null;
    }

    return is_file($tmpFile) && is_readable($tmpFile) ? $tmpFile : null;
}

function export_zip(): ?string
{
    return wiki_with_lock(
        fn() => export_zip_unlocked(),
        true,
        null,
    );
}
