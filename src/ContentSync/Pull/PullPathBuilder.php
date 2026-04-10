<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Illuminate\Support\Str;

class PullPathBuilder
{
    public function buildNodePath(string $contentPath, RemoteNode $node, int $order, string $parentPath = ''): string
    {
        $baseName = $this->prefixOrder($order) . '-' . $this->formatSlug($node->slug);

        if ($node->type === NodeType::Page) {
            $baseName .= '.md';
        }

        if ($parentPath === '') {
            return trim($contentPath, '/') . '/' . $baseName;
        }

        return rtrim($parentPath, '/') . '/' . $baseName;
    }

    public function buildMetaPath(string $nodePath): string
    {
        return rtrim($nodePath, '/') . '/_meta.yml';
    }

    protected function prefixOrder(int $order): string
    {
        return str_pad((string) $order, 2, '0', STR_PAD_LEFT);
    }

    protected function formatSlug(string $slug): string
    {
        $formatted = Str::slug($slug);

        return $formatted === '' ? 'untitled' : $formatted;
    }
}
