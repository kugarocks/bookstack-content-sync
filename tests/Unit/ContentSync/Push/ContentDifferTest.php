<?php

namespace Tests\Unit\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Push\ContentDiffer;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use PHPUnit\Framework\TestCase;

class ContentDifferTest extends TestCase
{
    public function test_detects_content_change()
    {
        $differ = new ContentDiffer();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/10-page.md',
            'entityId' => 12,
            'contentHash' => 'hash-b',
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/10-page.md',
            'entityId' => 12,
        ]);

        $diff = $differ->diff($localNode, $snapshotNode);

        $this->assertTrue($diff->contentChanged);
    }

    public function test_reports_no_content_change_for_equal_hash()
    {
        $differ = new ContentDiffer();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/10-page.md',
            'entityId' => 12,
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/10-page.md',
            'entityId' => 12,
        ]);

        $diff = $differ->diff($localNode, $snapshotNode);

        $this->assertFalse($diff->contentChanged);
    }
}
