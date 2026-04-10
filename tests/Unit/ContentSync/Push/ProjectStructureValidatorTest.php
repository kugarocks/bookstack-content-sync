<?php

namespace Tests\Unit\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Push\ProjectStructureValidator;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProjectStructureValidatorTest extends TestCase
{
    public function test_allows_valid_tree(): void
    {
        $validator = new ProjectStructureValidator();

        $validator->validate([
            PushNodeFactory::local(NodeType::Shelf, ['path' => 'content/10-shelf', 'entityId' => 1]),
            PushNodeFactory::local(NodeType::Book, ['path' => 'content/10-shelf/10-book', 'entityId' => 2]),
            PushNodeFactory::local(NodeType::Chapter, ['path' => 'content/10-shelf/10-book/10-chapter', 'entityId' => 3]),
            PushNodeFactory::local(NodeType::Page, ['path' => 'content/10-shelf/10-book/10-chapter/10-page.md', 'entityId' => 4]),
            PushNodeFactory::local(NodeType::Book, ['path' => 'content/20-book', 'entityId' => 5]),
            PushNodeFactory::local(NodeType::Page, ['path' => 'content/20-book/10-root-page.md', 'entityId' => 6]),
        ], 'content');

        $this->assertTrue(true);
    }

    public function test_rejects_duplicate_local_identity(): void
    {
        $validator = new ProjectStructureValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate local identity [page:12]');

        $validator->validate([
            PushNodeFactory::local(NodeType::Book, ['path' => 'content/10-book', 'entityId' => 2]),
            PushNodeFactory::local(NodeType::Page, ['path' => 'content/10-book/10-one.md', 'entityId' => 12]),
            PushNodeFactory::local(NodeType::Page, ['path' => 'content/10-book/20-two.md', 'entityId' => 12]),
        ], 'content');
    }

    public function test_rejects_top_level_page(): void
    {
        $validator = new ProjectStructureValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Top-level local node [content/10-page.md] must be a shelf or book');

        $validator->validate([
            PushNodeFactory::local(NodeType::Page, ['path' => 'content/10-page.md', 'entityId' => null]),
        ], 'content');
    }

    public function test_rejects_book_under_chapter(): void
    {
        $validator = new ProjectStructureValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Book [content/10-shelf/10-book/10-chapter/10-book] must be nested under a shelf or be a root node');

        $validator->validate([
            PushNodeFactory::local(NodeType::Shelf, ['path' => 'content/10-shelf', 'entityId' => 1]),
            PushNodeFactory::local(NodeType::Book, ['path' => 'content/10-shelf/10-book', 'entityId' => 2]),
            PushNodeFactory::local(NodeType::Chapter, ['path' => 'content/10-shelf/10-book/10-chapter', 'entityId' => 3]),
            PushNodeFactory::local(NodeType::Book, ['path' => 'content/10-shelf/10-book/10-chapter/10-book', 'entityId' => null]),
        ], 'content');
    }

    public function test_rejects_missing_parent(): void
    {
        $validator = new ProjectStructureValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parent node [content/10-book] not found for local node [content/10-book/10-page.md]');

        $validator->validate([
            PushNodeFactory::local(NodeType::Page, ['path' => 'content/10-book/10-page.md', 'entityId' => null]),
        ], 'other-content');
    }
}
