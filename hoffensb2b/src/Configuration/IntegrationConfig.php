<?php

namespace Hoffens\B2B\Configuration;

use Hoffens\B2B\Exception\ConfigurationException;

final class IntegrationConfig
{
    private $baseUrl;
    private $token;
    private $connectTimeout;
    private $timeout;
    private $retries;

    public function __construct($baseUrl, $token, $connectTimeout, $timeout, $retries = 2)
    {
        $baseUrl = rtrim((string) $baseUrl, '/') . '/';
        if (strpos($baseUrl, 'https://') !== 0) {
            throw new ConfigurationException('La API debe utilizar HTTPS.');
        }

        $this->baseUrl = $baseUrl;
        $this->token = trim((string) $token);
        $this->connectTimeout = max(1, (int) $connectTimeout);
        $this->timeout = max($this->connectTimeout, (int) $timeout);
        $this->retries = min(3, max(0, (int) $retries));
    }

    public function baseUrl() { return $this->baseUrl; }
    public function token() { return $this->token; }
    public function connectTimeout() { return $this->connectTimeout; }
    public function timeout() { return $this->timeout; }
    public function retries() { return $this->retries; }
    public function hasToken() { return $this->token !== ''; }
}
