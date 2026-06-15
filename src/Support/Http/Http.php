<?php

namespace MbpCoder\Payment\Support\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MbpCoder\Payment\Exceptions\GatewayException;

/**
 * A minimal, fluent HTTP client backed by Guzzle.
 *
 * It mirrors the small subset of Laravel's HTTP client API that the payment
 * providers use (acceptJson, withHeaders, withOptions, withUserAgent, asForm,
 * timeout, withoutVerifying, get, post) so the providers can stay
 * framework-agnostic while reading naturally.
 *
 * Any method can be used as an entry point statically, e.g.
 * Http::acceptJson()->post(...), thanks to __callStatic forwarding to a fresh
 * instance.
 */
class Http
{
    private array $headers = [];
    private array $options = ['http_errors' => false];
    private bool $asForm = false;

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return (new self())->{$name}(...$arguments);
    }

    public function acceptJson(): self
    {
        $this->headers['Accept'] = 'application/json';
        return $this;
    }

    public function asForm(): self
    {
        $this->asForm = true;
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
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

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        $this->headers['Authorization'] = trim($type . ' ' . $token);
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

    public function timeout(int $seconds): self
    {
        $this->options['timeout'] = $seconds;
        return $this;
    }

    public function connectTimeout(int $seconds): self
    {
        $this->options['connect_timeout'] = $seconds;
        return $this;
    }

    public function withoutVerifying(): self
    {
        $this->options['verify'] = false;
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
        $options[$this->asForm ? 'form_params' : 'json'] = $data;
        return $this->send('POST', $url, $options);
    }

    public function put(string $url, array $data = []): Response
    {
        $options = $this->buildOptions();
        $options[$this->asForm ? 'form_params' : 'json'] = $data;
        return $this->send('PUT', $url, $options);
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
        try {
            $client = new Client();
            return new Response($client->request($method, $url, $options));
        } catch (GuzzleException $e) {
            throw new GatewayException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
