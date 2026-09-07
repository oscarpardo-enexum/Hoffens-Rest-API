<?php

namespace Hoffens\B2B\Domain\Document;

use Hoffens\B2B\Exception\IntegrationException;

final class DocumentType
{
    private $value;

    public function __construct($value)
    {
        $value = strtoupper(trim((string) $value));
        if (!in_array($value, array('NV', 'GD', 'FC', 'NC', 'ND'), true)) {
            throw new IntegrationException('Tipo documental inválido.');
        }
        $this->value = $value;
    }

    public function value() { return $this->value; }
    public function __toString() { return $this->value; }
}

