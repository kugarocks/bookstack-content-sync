<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Push;

use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;

final readonly class PushProgressEvent
{
    private function __construct(
        public PushProgressStage $stage,
        public ?NodeType $type = null,
        public ?int $current = null,
        public ?int $total = null,
        public ?string $path = null,
        public ?string $message = null,
    ) {
    }

    public static function stage(PushProgressStage $stage): self
    {
        return new self($stage);
    }

    public static function warning(string $message): self
    {
        return new self(PushProgressStage::Warning, message: $message);
    }

    public static function create(NodeType $type, int $current, int $total, string $path): self
    {
        return new self(PushProgressStage::Create, $type, $current, $total, $path);
    }

    public static function update(NodeType $type, int $current, int $total, string $path): self
    {
        return new self(PushProgressStage::Update, $type, $current, $total, $path);
    }

    public static function syncShelfMembership(int $current, int $total, string $path): self
    {
        return new self(PushProgressStage::SyncShelfMembership, NodeType::Shelf, $current, $total, $path);
    }

    public static function trash(NodeType $type, int $current, int $total, string $path): self
    {
        return new self(PushProgressStage::Trash, $type, $current, $total, $path);
    }
}
