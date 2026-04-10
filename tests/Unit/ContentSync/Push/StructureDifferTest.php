<?php

namespace Tests\Unit\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Push\StructureDiffer;
use PHPUnit\Framework\TestCase;

class StructureDifferTest extends TestCase
{
    public function test_detects_path_change_without_parent_change()
    {
        $differ = new StructureDiffer();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/20-renamed.md',
            'entityId' => 12,
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/10-page.md',
            'entityId' => 12,
        ]);

        $diff = $differ->diff($localNode, $snapshotNode);

        $this->assertTrue($diff->pathChanged);
        $this->assertFalse($diff->parentChanged);
        $this->assertFalse($diff->orderChanged);
    }

    public function test_detects_parent_change()
    {
        $differ = new StructureDiffer();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/20-chapter/10-page.md',
            'entityId' => 12,
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/10-page.md',
            'entityId' => 12,
        ]);

        $diff = $differ->diff($localNode, $snapshotNode);

        $this->assertTrue($diff->pathChanged);
        $this->assertTrue($diff->parentChanged);
    }

    public function test_detects_order_change()
    {
        $differ = new StructureDiffer();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/10-page.md',
            'entityId' => 12,
            'order' => 20,
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/10-page.md',
            'entityId' => 12,
        ]);

        $diff = $differ->diff($localNode, $snapshotNode);

        $this->assertFalse($diff->pathChanged);
        $this->assertFalse($diff->parentChanged);
        $this->assertTrue($diff->orderChanged);
    }
}
