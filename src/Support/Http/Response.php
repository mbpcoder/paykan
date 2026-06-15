<?php

namespace MbpCoder\Payment\Support\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Thin wrapper around a PSR-7 response exposing the small, framework-agnostic
 * surface the payment providers rely on (json(), object(), ok(), status()).
 */
class Response
{
    private string $body;

    public function __construct(private ResponseInterface $response)
    {
        $this->body = (string) $response->getBody();
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function ok(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function successful(): bool
    {
        return $this->ok();
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decode the response body as an associative array.
     *
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = json_decode($this->body, true);
        if ($key === null) {
            return $decoded;
        }
        return is_array($decoded) && array_key_exists($key, $decoded) ? $decoded[$key] : $default;
    }

    /**
     * Decode the response body as a stdClass object graph.
     */
    public function object(): mixed
    {
        return json_decode($this->body, false);
    }
}
