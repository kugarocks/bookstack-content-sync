<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Shared;

class PageMarkdownCodec
{
    public const EMPTY_PAGE_REMOTE_PLACEHOLDER = '<!-- bookstack-content-sync:empty-page:v1 -->';

    public function encodeForRemote(string $markdown): string
    {
        $normalized = $this->normalizeLineEndings($markdown);

        return trim($normalized) === '' ? self::EMPTY_PAGE_REMOTE_PLACEHOLDER : $normalized;
    }

    public function decodeFromRemote(string $markdown): string
    {
        $normalized = $this->normalizeLineEndings($markdown);

        return $this->isEncodedEmptyPlaceholder($normalized) ? '' : $normalized;
    }

    public function canonicalizeForHash(string $markdown): string
    {
        return $this->decodeFromRemote($markdown);
    }

    public function normalizeLineEndings(string $markdown): string
    {
        return str_replace(["\r\n", "\r"], "\n", $markdown);
    }

    public function isEncodedEmptyPlaceholder(string $markdown): bool
    {
        return rtrim($this->normalizeLineEndings($markdown), "\n") === self::EMPTY_PAGE_REMOTE_PLACEHOLDER;
    }
}
