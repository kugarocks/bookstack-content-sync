<?php

namespace Tests\Unit\ContentSync\Pull;

use KugaRocks\BookStackContentSync\ContentSync\Pull\BookStackApiClient;
use BookStack\Http\HttpRequestService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BookStackApiClientTest extends TestCase
{
    public function test_list_all_paginates_and_sends_auth_header()
    {
        $http = new HttpRequestService();
        $history = $http->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [['id' => 1], ['id' => 2]],
                'total' => 3,
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [['id' => 3]],
                'total' => 3,
            ])),
        ], false);

        $client = new BookStackApiClient($http, pageSize: 2);
        $items = $client->listAll('https://docs.example.com', 'books', 'token-id', 'token-secret');

        $this->assertCount(3, $items);
        $this->assertStringContainsString('/api/books?count=2&offset=0', (string) $history->requestAt(0)?->getUri());
        $this->assertSame('Token token-id:token-secret', $history->requestAt(0)?->getHeaderLine('Authorization'));
        $this->assertStringContainsString('/api/books?count=2&offset=2', (string) $history->latestRequest()?->getUri());
    }

    public function test_read_text_returns_raw_response_body()
    {
        $http = new HttpRequestService();
        $http->mockClient([
            new Response(200, ['Content-Type' => 'text/plain'], "# Heading\n\nBody"),
        ], false);

        $client = new BookStackApiClient($http);
        $body = $client->readText('https://docs.example.com', 'pages/4/export/markdown', 'token-id', 'token-secret');

        $this->assertSame("# Heading\n\nBody", $body);
    }
}
