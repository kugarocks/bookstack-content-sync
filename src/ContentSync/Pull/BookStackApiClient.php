<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Pull;

use BookStack\Http\HttpRequestService;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class BookStackApiClient
{
    public function __construct(
        protected HttpRequestService $http,
        protected int $timeout = 30,
        protected int $pageSize = 500,
    ) {
    }

    public function read(string $baseUrl, string $path, string $tokenId, string $tokenSecret): array
    {
        $response = $this->send($baseUrl, $path, $tokenId, $tokenSecret);
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Failed to decode JSON response from [{$path}]", previous: $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException("Expected JSON object response from [{$path}]");
        }

        return $data;
    }

    public function readText(string $baseUrl, string $path, string $tokenId, string $tokenSecret): string
    {
        $response = $this->send($baseUrl, $path, $tokenId, $tokenSecret);

        return (string) $response->getBody();
    }

    public function listAll(string $baseUrl, string $path, string $tokenId, string $tokenSecret): array
    {
        $offset = 0;
        $results = [];

        do {
            $response = $this->read(
                $baseUrl,
                $path . '?' . http_build_query([
                    'count' => $this->pageSize,
                    'offset' => $offset,
                ]),
                $tokenId,
                $tokenSecret,
            );

            $data = $response['data'] ?? null;
            $total = $response['total'] ?? null;

            if (!is_array($data) || !is_int($total)) {
                throw new RuntimeException("Expected paginated list response from [{$path}]");
            }

            $results = array_merge($results, $data);
            $offset += count($data);
        } while ($offset < $total);

        return $results;
    }

    protected function send(string $baseUrl, string $path, string $tokenId, string $tokenSecret)
    {
        $client = $this->http->buildClient($this->timeout);
        $request = new Request('GET', $this->buildUrl($baseUrl, $path), [
            'Accept' => 'application/json',
            'Authorization' => "Token {$tokenId}:{$tokenSecret}",
        ]);
        $response = $client->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException("BookStack API request failed for [{$path}] with status [{$response->getStatusCode()}]");
        }

        return $response;
    }

    protected function buildUrl(string $baseUrl, string $path): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $path = ltrim($path, '/');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('BookStack API base URL cannot be empty');
        }

        return "{$baseUrl}/api/{$path}";
    }
}
