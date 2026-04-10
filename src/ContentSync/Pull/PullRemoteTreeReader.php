<?php

namespace Kugarocks\BookStackContentSync\ContentSync\Pull;

interface PullRemoteTreeReader
{
    /**
     * @return RemoteNode[]
     */
    public function read(SyncConfig $config): array;
}
