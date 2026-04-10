<?php

namespace Tests\Unit\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Pull\ExportFileKind;
use KugaRocks\BookStackContentSync\ContentSync\Pull\ExportFilePlan;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PullContentRunner;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PullRemoteTreeReader;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PullResult;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PullResultBuilder;
use KugaRocks\BookStackContentSync\ContentSync\Pull\PullResultWriter;
use KugaRocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use KugaRocks\BookStackContentSync\ContentSync\Shared\NodeType;
use KugaRocks\BookStackContentSync\ContentSync\Shared\SnapshotNode;
use PHPUnit\Framework\TestCase;

class PullContentRunnerTest extends TestCase
{
    public function test_runs_pull_pipeline_from_project_root()
    {
        $loader = $this->createMock(SyncConfigLoader::class);
        $reader = $this->createMock(PullRemoteTreeReader::class);
        $builder = $this->createMock(PullResultBuilder::class);
        $writer = $this->createMock(PullResultWriter::class);

        $config = PullNodeFactory::config();
        $remoteNodes = [PullNodeFactory::node(NodeType::Book)];
        $result = new PullResult(
            [new ExportFilePlan('content/01-book/_meta.yml', ExportFileKind::Meta, 'meta')],
            [new SnapshotNode(NodeType::Book, 1, '01-book', 1, 'hash', null, 'book', 'Book')]
        );

        $loader->expects($this->once())
            ->method('load')
            ->with('/tmp/project/sync.json')
            ->willReturn($config);
        $reader->expects($this->once())
            ->method('read')
            ->with($config)
            ->willReturn($remoteNodes);
        $builder->expects($this->once())
            ->method('build')
            ->with($config, $remoteNodes)
            ->willReturn($result);
        $writer->expects($this->once())
            ->method('write')
            ->with('/tmp/project', $config, $result);

        $runner = new PullContentRunner($loader, $reader, $builder, $writer);

        $this->assertSame($result, $runner->run('/tmp/project'));
    }
}
