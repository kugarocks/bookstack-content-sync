<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

readonly class SyncConfig
{
    public function __construct(
        public int $version,
        public string $appUrl,
        public string $contentPath,
        public string $tokenIdEnvVar,
        public string $tokenSecretEnvVar,
    ) {
    }
}
