<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

use KugaRocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;

class PushProjectStateLoader
{
    public function __construct(
        protected SyncConfigLoader $syncConfigLoader,
        protected SnapshotFileLoader $snapshotFileLoader,
        protected LocalContentScanner $localContentScanner,
        protected ProjectStructureValidator $projectStructureValidator,
    ) {
    }

    public function load(string $projectRootPath): PushProjectState
    {
        $projectRootPath = rtrim($projectRootPath, '/');
        $config = $this->syncConfigLoader->load($projectRootPath . '/sync.json');
        $snapshotNodes = $this->snapshotFileLoader->load($projectRootPath . '/snapshot.json');
        $localNodes = $this->localContentScanner->scan($projectRootPath, $config->contentPath);
        $this->projectStructureValidator->validate($localNodes, $config->contentPath);

        return new PushProjectState($config, $localNodes, $snapshotNodes);
    }
}
