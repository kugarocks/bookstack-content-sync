<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\BookStackApiClient;
use Kugarocks\BookStackContentSync\ContentSync\Pull\BookStackApiRemoteTreeReader;
use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigEnvCredentialResolver;
use Kugarocks\BookStackContentSync\ContentSync\Shared\NodeType;
use PHPUnit\Framework\TestCase;

class BookStackApiRemoteTreeReaderTest extends TestCase
{
    public function test_reads_shelf_book_chapter_page_tree_from_api()
    {
        $client = $this->createMock(BookStackApiClient::class);
        $config = PullNodeFactory::config([
            'appUrl' => 'https://docs.example.com',
            'tokenIdEnvVar' => 'PULL_TOKEN_ID',
            'tokenSecretEnvVar' => 'PULL_TOKEN_SECRET',
        ]);

        $client->method('listAll')
            ->willReturnMap([
                ['https://docs.example.com', 'shelves', 'token-id', 'token-secret', [['id' => 1]]],
                ['https://docs.example.com', 'books', 'token-id', 'token-secret', [['id' => 2], ['id' => 9]]],
            ]);

        $client->method('read')
            ->willReturnMap([
                ['https://docs.example.com', 'shelves/1', 'token-id', 'token-secret', [
                    'id' => 1,
                    'name' => 'Blog',
                    'slug' => 'blog',
                    'description' => '',
                    'tags' => [['name' => 'topic', 'value' => 'dev']],
                    'books' => [['id' => 2, 'name' => '2026', 'slug' => '2026']],
                ]],
                ['https://docs.example.com', 'books/2', 'token-id', 'token-secret', [
                    'id' => 2,
                    'name' => '2026',
                    'slug' => '2026',
                    'description' => 'Year notes',
                    'tags' => [['name' => 'series', 'value' => 'neovim']],
                    'contents' => [[
                        'id' => 3,
                        'type' => 'chapter',
                        'name' => 'Neovim',
                        'slug' => 'neovim',
                        'priority' => 7,
                        'pages' => [[
                            'id' => 4,
                            'name' => 'Quick Start',
                            'slug' => 'quick-start',
                            'priority' => 2,
                        ]],
                    ]],
                ]],
                ['https://docs.example.com', 'chapters/3', 'token-id', 'token-secret', [
                    'id' => 3,
                    'name' => 'Neovim',
                    'slug' => 'neovim',
                    'description' => 'Editor notes',
                    'priority' => 7,
                    'tags' => [['name' => 'level', 'value' => 'advanced']],
                ]],
                ['https://docs.example.com', 'pages/4', 'token-id', 'token-secret', [
                    'id' => 4,
                    'name' => 'Quick Start',
                    'slug' => 'quick-start',
                    'priority' => 2,
                    'markdown' => "## Install\n",
                    'tags' => [['name' => 'quick-start', 'value' => '']],
                ]],
                ['https://docs.example.com', 'books/9', 'token-id', 'token-secret', [
                    'id' => 9,
                    'name' => 'Loose Book',
                    'slug' => 'loose-book',
                    'description' => '',
                    'tags' => [],
                    'contents' => [[
                        'id' => 10,
                        'type' => 'page',
                        'name' => 'Loose Page',
                        'slug' => 'loose-page',
                        'priority' => 4,
                    ]],
                ]],
                ['https://docs.example.com', 'pages/10', 'token-id', 'token-secret', [
                    'id' => 10,
                    'name' => 'Loose Page',
                    'slug' => 'loose-page',
                    'priority' => 4,
                    'markdown' => "Loose body\n",
                    'tags' => [],
                ]],
            ]);

        $reader = new BookStackApiRemoteTreeReader($client, new SyncConfigEnvCredentialResolver());
        $messages = [];
        $reader->setProgressCallback(function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->runWithEnv(['PULL_TOKEN_ID' => 'token-id', 'PULL_TOKEN_SECRET' => 'token-secret'], function () use ($reader, $config, &$messages) {
            $roots = $reader->read($config);

            $this->assertCount(2, $roots);
            $this->assertSame(NodeType::Shelf, $roots[0]->type);
            $this->assertSame('Blog', $roots[0]->name);
            $this->assertSame('dev', $roots[0]->tags[0]->value);
            $this->assertSame('topic', $roots[0]->tags[0]->name);
            $this->assertSame(NodeType::Book, $roots[0]->children[0]->type);
            $this->assertSame(NodeType::Chapter, $roots[0]->children[0]->children[0]->type);
            $this->assertSame("## Install\n", $roots[0]->children[0]->children[0]->children[0]->markdown);
            $this->assertSame('quick-start', $roots[0]->children[0]->children[0]->children[0]->tags[0]->name);
            $this->assertSame('', $roots[0]->children[0]->children[0]->children[0]->tags[0]->value);

            $this->assertSame(NodeType::Book, $roots[1]->type);
            $this->assertSame('Loose Book', $roots[1]->name);
            $this->assertSame('Loose body' . "\n", $roots[1]->children[0]->markdown);
            $this->assertContains('Pulling shelf: Blog', $messages);
            $this->assertContains('Pulling book: 2026', $messages);
            $this->assertContains('Pulling chapter: Neovim', $messages);
            $this->assertContains('Pulling page: Quick Start', $messages);
            $this->assertContains('Pulling book: Loose Book', $messages);
            $this->assertContains('Pulling page: Loose Page', $messages);
        });
    }

    protected function runWithEnv(array $valuesByKey, callable $callback): void
    {
        $originals = [];

        foreach ($valuesByKey as $key => $value) {
            $originals[$key] = $_SERVER[$key] ?? null;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            $callback();
        } finally {
            foreach ($originals as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                    putenv($key);
                    continue;
                }

                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
