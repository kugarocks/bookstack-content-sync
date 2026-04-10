<?php

namespace Tests\Unit\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Push\LocalNode;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use Kugarocks\BookStackContentSync\ContentSync\Push\SnapshotMatcher;
use Kugarocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SnapshotMatcherTest extends TestCase
{
    public function test_matches_nodes_by_type_and_entity_id()
    {
        $matcher = new SnapshotMatcher();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/10-page.md',
            'entityId' => 12,
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/20-page.md',
            'entityId' => 12,
            'position' => 20,
            'contentHash' => 'hash-b',
        ]);

        $results = $matcher->match([$localNode], [$snapshotNode]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isMatched());
        $this->assertSame($snapshotNode, $results[0]->snapshotNode);
    }

    public function test_does_not_match_when_local_node_has_no_entity_id()
    {
        $matcher = new SnapshotMatcher();
        $localNode = PushNodeFactory::local(NodeType::Page, [
            'path' => '10-book/10-page.md',
            'entityId' => null,
        ]);
        $snapshotNode = PushNodeFactory::snapshot(NodeType::Page, [
            'file' => '10-book/10-page.md',
            'entityId' => 12,
        ]);

        $results = $matcher->match([$localNode], [$snapshotNode]);

        $this->assertFalse($results[0]->isMatched());
        $this->assertNull($results[0]->snapshotNode);
    }

    public function test_throws_on_duplicate_local_identities()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate local identity [page:12]');

        $matcher = new SnapshotMatcher();
        $matcher->buildLocalMap([
            PushNodeFactory::local(NodeType::Page, [
                'path' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
            PushNodeFactory::local(NodeType::Page, [
                'path' => '10-book/20-page.md',
                'entityId' => 12,
                'order' => 20,
                'contentHash' => 'hash-b',
            ]),
        ]);
    }

    public function test_throws_on_duplicate_snapshot_identities()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate snapshot identity [page:12]');

        $matcher = new SnapshotMatcher();
        $matcher->buildSnapshotMap([
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-book/10-page.md',
                'entityId' => 12,
            ]),
            PushNodeFactory::snapshot(NodeType::Page, [
                'file' => '10-book/20-page.md',
                'entityId' => 12,
                'position' => 20,
                'contentHash' => 'hash-b',
            ]),
        ]);
    }
}
