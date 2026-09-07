<?php

namespace Hoffens\B2B\Http;

final class HttpResponse
{
    private $statusCode;
    private $headers;
    private $body;
    private $metrics;

    public function __construct($statusCode, array $headers, $body, array $metrics = array())
    {
        $this->statusCode = (int) $statusCode;
        $this->headers = $headers;
        $this->body = (string) $body;
        $this->metrics = $metrics;
    }

    public function statusCode() { return $this->statusCode; }
    public function headers() { return $this->headers; }
    public function body() { return $this->body; }
    public function metrics() { return $this->metrics; }
    public function isSuccessful() { return $this->statusCode >= 200 && $this->statusCode < 300; }
}
