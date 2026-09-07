<?php

namespace Hoffens\B2B\Http;

final class HttpRequest
{
    private $method;
    private $path;
    private $headers;
    private $body;

    public function __construct($method, $path, array $headers = array(), $body = null)
    {
        $this->method = strtoupper((string) $method);
        $this->path = ltrim((string) $path, '/');
        $this->headers = $headers;
        $this->body = $body;
    }

    public function method() { return $this->method; }
    public function path() { return $this->path; }
    public function headers() { return $this->headers; }
    public function body() { return $this->body; }
}

