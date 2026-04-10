<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

class PullContentRunner
{
    public function __construct(
        protected SyncConfigLoader $syncConfigLoader,
        protected PullRemoteTreeReader $remoteTreeReader,
        protected PullResultBuilder $pullResultBuilder,
        protected PullResultWriter $pullResultWriter,
    ) {
    }

    public function run(string $projectRootPath, ?callable $progress = null): PullResult
    {
        $projectRootPath = rtrim($projectRootPath, '/');
        if ($progress !== null) {
            $progress('Loading sync config');
        }
        $config = $this->syncConfigLoader->load($projectRootPath . '/sync.json');
        $this->pullResultWriter->assertWritableTargetsAreEmpty($projectRootPath, $config);
        if ($this->remoteTreeReader instanceof BookStackApiRemoteTreeReader) {
            $this->remoteTreeReader->setProgressCallback($progress);
        }
        if ($progress !== null) {
            $progress('Reading remote content tree');
        }
        $remoteNodes = $this->remoteTreeReader->read($config);
        if ($progress !== null) {
            $progress('Building local export plan');
        }
        $result = $this->pullResultBuilder->build($config, $remoteNodes);
        if ($progress !== null) {
            $progress('Writing pulled files to disk');
        }
        $this->pullResultWriter->write($projectRootPath, $config, $result);

        return $result;
    }
}
