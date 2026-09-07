<?php

namespace Hoffens\B2B\Application\Diagnostics;

final class EndpointCheckResult
{
    private $endpoint;
    private $successful;
    private $statusCode;
    private $elapsedMs;
    private $summary;
    private $error;

    private function __construct($endpoint, $successful, $statusCode, $elapsedMs, array $summary, $error)
    {
        $this->endpoint = (string) $endpoint;
        $this->successful = (bool) $successful;
        $this->statusCode = (int) $statusCode;
        $this->elapsedMs = (int) $elapsedMs;
        $this->summary = $summary;
        $this->error = (string) $error;
    }

    public static function success($endpoint, $elapsedMs, array $summary)
    {
        return new self($endpoint, true, 200, $elapsedMs, $summary, '');
    }

    public static function failure($endpoint, $statusCode, $elapsedMs, $error)
    {
        return new self($endpoint, false, $statusCode, $elapsedMs, array(), $error);
    }

    public function endpoint() { return $this->endpoint; }
    public function isSuccessful() { return $this->successful; }
    public function statusCode() { return $this->statusCode; }
    public function elapsedMs() { return $this->elapsedMs; }
    public function summary() { return $this->summary; }
    public function error() { return $this->error; }
}

