<?php

namespace Hoffens\B2B\Domain\Pricing;

use Hoffens\B2B\Exception\IntegrationException;

final class CustomerPrice implements \JsonSerializable
{
    private $itemCode;
    private $currency;
    private $amount;

    public function __construct($itemCode, $currency, $amount)
    {
        $itemCode = trim((string) $itemCode);
        $currency = strtoupper(trim((string) $currency));
        if ($itemCode === '' || $currency === '' || (float) $amount <= 0) {
            throw new IntegrationException('Precio de cliente inválido.');
        }
        $this->itemCode = $itemCode;
        $this->currency = $currency;
        $this->amount = (float) $amount;
    }

    public function itemCode() { return $this->itemCode; }
    public function currency() { return $this->currency; }
    public function amount() { return $this->amount; }

    public function jsonSerialize()
    {
        return array('itemCode' => $this->itemCode, 'moneda' => $this->currency, 'precio' => $this->amount);
    }
}

