<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

interface PullRemoteTreeReader
{
    /**
     * @return RemoteNode[]
     */
    public function read(SyncConfig $config): array;
}
