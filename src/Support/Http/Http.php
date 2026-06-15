<?php

namespace MbpCoder\IranPayment\Support\Http;

use GuzzleHttp\Client;

/**
 * A minimal, fluent HTTP client backed by Guzzle.
 *
 * It mirrors the small subset of Laravel's HTTP client API that the payment
 * providers use (acceptJson, withHeaders, withOptions, withUserAgent, get,
 * post) so the providers can stay framework-agnostic while reading naturally.
 *
 * Any method can be used as an entry point statically, e.g.
 * Http::acceptJson()->post(...), thanks to __callStatic forwarding to a fresh
 * instance.
 */
class Http
{
    private array $headers = [];
    private array $options = ['http_errors' => false];

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return (new self())->{$name}(...$arguments);
    }

    public function acceptJson(): self
    {
        $this->headers['Accept'] = 'application/json';
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function withUserAgent(string $userAgent): self
    {
        $this->headers['User-Agent'] = $userAgent;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function get(string $url, array $query = []): Response
    {
        $options = $this->buildOptions();
        if ($query) {
            $options['query'] = $query;
        }
        return $this->send('GET', $url, $options);
    }

    public function post(string $url, array $data = []): Response
    {
        $options = $this->buildOptions();
        $options['json'] = $data;
        return $this->send('POST', $url, $options);
    }

    private function buildOptions(): array
    {
        $options = $this->options;
        if ($this->headers) {
            $options['headers'] = array_merge($options['headers'] ?? [], $this->headers);
        }
        return $options;
    }

    private function send(string $method, string $url, array $options): Response
    {
        $client = new Client();
        return new Response($client->request($method, $url, $options));
    }
}
