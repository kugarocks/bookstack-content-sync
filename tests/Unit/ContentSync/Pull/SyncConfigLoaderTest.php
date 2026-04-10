<?php

namespace Tests\Unit\ContentSync\Pull;

use Kugarocks\BookStackContentSync\ContentSync\Pull\SyncConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SyncConfigLoaderTest extends TestCase
{
    public function test_loads_sync_config_from_json_file()
    {
        $path = $this->createTempFile(json_encode([
            'version' => 1,
            'app_url' => 'https://www.example.com',
            'content_path' => '/content/',
            'env_vars' => [
                'token_id' => 'TOKEN_ID',
                'token_secret' => 'TOKEN_SECRET',
            ],
        ], JSON_PRETTY_PRINT));

        $config = (new SyncConfigLoader())->load($path);

        $this->assertSame(1, $config->version);
        $this->assertSame('https://www.example.com', $config->appUrl);
        $this->assertSame('content', $config->contentPath);
        $this->assertSame('TOKEN_ID', $config->tokenIdEnvVar);
        $this->assertSame('TOKEN_SECRET', $config->tokenSecretEnvVar);

        unlink($path);
    }

    public function test_throws_for_missing_required_field()
    {
        $path = $this->createTempFile(json_encode([
            'version' => 1,
            'app_url' => 'https://www.example.com',
            'content_path' => 'content',
            'env_vars' => [
                'token_id' => 'TOKEN_ID',
            ],
        ], JSON_PRETTY_PRINT));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('token_secret');

        try {
            (new SyncConfigLoader())->load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_throws_for_invalid_json()
    {
        $path = $this->createTempFile('{invalid-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        try {
            (new SyncConfigLoader())->load($path);
        } finally {
            unlink($path);
        }
    }

    protected function createTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sync-config-');
        file_put_contents($path, $contents);

        return $path;
    }
}
