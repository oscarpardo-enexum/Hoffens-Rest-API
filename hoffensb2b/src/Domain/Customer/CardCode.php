<?php

namespace Hoffens\B2B\Domain\Customer;

use Hoffens\B2B\Exception\IntegrationException;

final class CardCode
{
    private $value;

    public function __construct($value)
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new IntegrationException('CardCode inválido.');
        }
        $this->value = $value;
    }

    public function value() { return $this->value; }
    public function __toString() { return $this->value; }
}

