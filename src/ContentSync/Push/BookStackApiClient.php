<?php

namespace KugaRocks\BookStackContentSync\ContentSync\Push;

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
    ) {
    }

    public function create(string $baseUrl, string $path, string $tokenId, string $tokenSecret, array $payload): array
    {
        return $this->sendJson('POST', $baseUrl, $path, $tokenId, $tokenSecret, $payload);
    }

    public function update(string $baseUrl, string $path, string $tokenId, string $tokenSecret, array $payload): array
    {
        return $this->sendJson('PUT', $baseUrl, $path, $tokenId, $tokenSecret, $payload);
    }

    public function delete(string $baseUrl, string $path, string $tokenId, string $tokenSecret): void
    {
        $this->send('DELETE', $baseUrl, $path, $tokenId, $tokenSecret);
    }

    protected function sendJson(string $method, string $baseUrl, string $path, string $tokenId, string $tokenSecret, array $payload): array
    {
        $body = json_encode($payload);
        if ($body === false) {
            throw new RuntimeException("Failed to encode JSON request body for [{$path}]");
        }

        $response = $this->send($method, $baseUrl, $path, $tokenId, $tokenSecret, $body);
        $rawBody = (string) $response->getBody();

        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Failed to decode JSON response from [{$path}]", previous: $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException("Expected JSON object response from [{$path}]");
        }

        return $data;
    }

    protected function send(
        string $method,
        string $baseUrl,
        string $path,
        string $tokenId,
        string $tokenSecret,
        ?string $body = null,
    ) {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => "Token {$tokenId}:{$tokenSecret}",
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $client = $this->http->buildClient($this->timeout);
        $request = new Request($method, $this->buildUrl($baseUrl, $path), $headers, $body);
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
