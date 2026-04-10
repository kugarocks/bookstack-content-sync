<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

readonly class ExportFilePlan
{
    public function __construct(
        public string $path,
        public ExportFileKind $kind,
        public string $contents,
    ) {
    }
}
