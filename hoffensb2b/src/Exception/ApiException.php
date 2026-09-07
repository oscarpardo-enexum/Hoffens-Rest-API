<?php

namespace Hoffens\B2B\Exception;

class ApiException extends IntegrationException
{
    private $statusCode;

    public function __construct($message, $statusCode = 0, \Exception $previous = null)
    {
        parent::__construct($message, (int) $statusCode, $previous);
        $this->statusCode = (int) $statusCode;
    }

    public function statusCode() { return $this->statusCode; }
}

