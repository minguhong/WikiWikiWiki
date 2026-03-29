<?php

declare(strict_types=1);

function format_markdown_content(string $content): string
{
    $lines = explode("\n", $content);
    $formatted = [];
    $prevLine = '';
    $inCodeBlock = false;
    $fenceChar = '';
    $fenceLength = 0;

    foreach ($lines as $line) {
        if (preg_match('/^\s*(`{3,}|~{3,})(.*)$/', $line, $fenceMatch)) {
            $candidateFence = $fenceMatch[1];
            $candidateChar = $candidateFence[0];
            $candidateLength = strlen($candidateFence);
            $fenceTail = trim((string) $fenceMatch[2]);

            if (!$inCodeBlock) {
                $inCodeBlock = true;
                $fenceChar = $candidateChar;
                $fenceLength = $candidateLength;
                $formatted[] = $line;
                $prevLine = $line;
                continue;
            }

            $isFenceOnlyLine = $fenceTail === '';
            $isMatchingFence = $candidateChar === $fenceChar && $candidateLength >= $fenceLength;
            if ($isFenceOnlyLine && $isMatchingFence) {
                $inCodeBlock = false;
                $fenceChar = '';
                $fenceLength = 0;
                $formatted[] = $line;
                $prevLine = $line;
                continue;
            }
        }

        if ($inCodeBlock) {
            $formatted[] = $line;
            $prevLine = $line;
            continue;
        }

        
        if (preg_match('/^(?: {4}|\t)/', $line) === 1) {
            $formatted[] = $line;
            $prevLine = $line;
            continue;
        }

        $line = preg_replace('/^(#{1,6})\s+/', '$1 ', $line) ?? $line;
        $line = preg_replace('/^(\s*)(>+)\s*/', '$1$2 ', $line) ?? $line;

        $isHRDash = preg_match('/^\s*-{3,}\s*$/', $line);
        if (!$isHRDash) {
            $line = preg_replace('/^(\s*)(-)\s+/', '$1$2 ', $line) ?? $line;
        }

        $line = preg_replace('/^(\s*)(\+)\s+/', '$1$2 ', $line) ?? $line;

        $isHR = preg_match('/^\s*\*{3,}\s*$/', $line);
        $isEmphasis = preg_match('/^\s*\*+[^\s*].*\*+/', $line);
        if (!$isHR && !$isEmphasis) {
            $line = preg_replace('/^(\s*)(\*)\s+/', '$1$2 ', $line) ?? $line;
        }

        $line = preg_replace('/^(\s*)(\d+\.)\s+/', '$1$2 ', $line) ?? $line;

        if (
            preg_match('/^([ \t]*)((?:[-+*]|\d+\.))\s+/', $prevLine, $prevListMatch) === 1
            && preg_match('/^([ \t]+)((?:[-+*]|\d+\.)\s+.*)$/', $line, $currentListMatch) === 1
        ) {
            $prevIndentWidth = strlen((string) $prevListMatch[1]);
            $currentIndentWidth = strlen((string) $currentListMatch[1]);
            if ($currentIndentWidth > $prevIndentWidth) {
                $minimumNestedIndent = $prevIndentWidth + strlen((string) $prevListMatch[2]) + 1;
                if ($currentIndentWidth < $minimumNestedIndent) {
                    $line = str_repeat(' ', $minimumNestedIndent) . (string) $currentListMatch[2];
                }
            }
        }

        $currentLine = trim($line);
        $prevLineTrimmed = trim($prevLine);
        $isIndentedListLine = preg_match('/^[ \t]+(?:[-+*]|\d+\.)\s+/', $line) === 1;
        $isPrevIndentedListLine = preg_match('/^[ \t]+(?:[-+*]|\d+\.)\s+/', $prevLine) === 1;

        if (preg_match('/^#{1,6}\s/', $currentLine) && $prevLineTrimmed !== '' && $formatted !== []) {
            $formatted[] = '';
        }

        if (
            !$isIndentedListLine
            && !$isPrevIndentedListLine
            && preg_match('/^[-*+]\s/', $currentLine)
            && $prevLineTrimmed !== ''
            && !preg_match('/^[-*+]\s/', $prevLineTrimmed)
        ) {
            $formatted[] = '';
        }

        if (
            !$isIndentedListLine
            && !$isPrevIndentedListLine
            && preg_match('/^\d+\.\s/', $currentLine)
            && $prevLineTrimmed !== ''
            && !preg_match('/^\d+\.\s/', $prevLineTrimmed)
        ) {
            $formatted[] = '';
        }

        $formatted[] = $line;

        if (preg_match('/^#{1,6}\s/', $currentLine)) {
            $formatted[] = '';
        }

        $prevLine = $line;
    }

    $result = implode("\n", $formatted);
    $result = preg_replace("/\n{3,}/", "\n\n", $result) ?? $result;
    return rtrim($result, "\n");
}

function strip_code_blocks(string $content): string
{
    $content = preg_replace('/```[\s\S]*?```/', '', $content) ?? $content;
    $content = preg_replace('/~~~[\s\S]*?~~~/', '', $content) ?? $content;
    $subject = $content;
    $content = preg_replace_callback(
        '/^(?: {4}|\t)(?![ \t]*(?:[-+*]|\d+\.)\s).*(?:\R(?: {4}|\t)(?![ \t]*(?:[-+*]|\d+\.)\s).*)*/m',
        static function (array $m) use ($subject): string {
            $block = $m[0][0];
            $offset = $m[0][1];
            $firstLine = (string) (preg_split('/\R/', $block, 2)[0] ?? '');
            if (!markdown_should_preserve_indented_code_block($subject, $offset, $firstLine)) {
                return $block;
            }
            return '';
        },
        $subject,
        -1,
        $count,
        PREG_OFFSET_CAPTURE,
    ) ?? $content;

    $length = strlen($content);
    $result = '';
    $offset = 0;
    while ($offset < $length) {
        $tickPos = strpos($content, '`', $offset);
        if ($tickPos === false) {
            $result .= substr($content, $offset);
            break;
        }
        $result .= substr($content, $offset, $tickPos - $offset);

        $delimiterLength = 1;
        while ($tickPos + $delimiterLength < $length && $content[$tickPos + $delimiterLength] === '`') {
            $delimiterLength++;
        }
        $delimiter = str_repeat('`', $delimiterLength);

        $lineEnd = $length;
        $newlinePos = strpos($content, "\n", $tickPos);
        if ($newlinePos !== false) {
            $lineEnd = $newlinePos;
        }
        $carriagePos = strpos($content, "\r", $tickPos);
        if ($carriagePos !== false && $carriagePos < $lineEnd) {
            $lineEnd = $carriagePos;
        }

        $closePos = strpos($content, $delimiter, $tickPos + $delimiterLength);
        if ($closePos === false || $closePos >= $lineEnd) {
            $result .= $content[$tickPos];
            $offset = $tickPos + 1;
            continue;
        }

        $offset = $closePos + $delimiterLength;
    }

    return $result;
}
