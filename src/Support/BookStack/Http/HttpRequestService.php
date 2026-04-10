<?php

namespace Kugarocks\BookStackContentSync\Support\BookStack\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

class HttpRequestService
{
    protected ?HandlerStack $handler = null;

    public function buildClient(int $timeout, array $options = []): ClientInterface
    {
        $defaultOptions = [
            'timeout' => $timeout,
            'handler' => $this->handler,
        ];

        return new Client(array_merge($options, $defaultOptions));
    }

    public function jsonRequest(string $method, string $uri, array $data): GuzzleRequest
    {
        $headers = ['Content-Type' => 'application/json'];

        return new GuzzleRequest($method, $uri, $headers, json_encode($data));
    }

    public function mockClient(array $responses = [], bool $pad = true): HttpClientHistory
    {
        if ($pad) {
            $response = new Response(200, [], 'success');
            $responses = array_merge($responses, array_fill(0, 10, $response));
        }

        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler($responses);
        $this->handler = HandlerStack::create($mock);
        $this->handler->push($history, 'history');

        return new HttpClientHistory($container);
    }

    public function clearMocking(): void
    {
        $this->handler = null;
    }
}
