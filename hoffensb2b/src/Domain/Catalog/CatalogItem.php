<?php

namespace Hoffens\B2B\Domain\Catalog;

use Hoffens\B2B\Exception\IntegrationException;

final class CatalogItem implements \JsonSerializable
{
    private $itemCode;
    private $name;
    private $purchaseMultiple;
    private $saleMultiple;
    private $stock;
    private $metadata;

    public function __construct($itemCode, $name, $purchaseMultiple, $saleMultiple, $stock, array $metadata = array())
    {
        $itemCode = trim((string) $itemCode);
        if ($itemCode === '') {
            throw new IntegrationException('El artículo debe tener itemCode.');
        }
        if ((float) $purchaseMultiple <= 0 || (float) $saleMultiple <= 0) {
            throw new IntegrationException('Los múltiplos del artículo deben ser positivos.');
        }
        $this->itemCode = $itemCode;
        $this->name = trim((string) $name);
        $this->purchaseMultiple = (float) $purchaseMultiple;
        $this->saleMultiple = (float) $saleMultiple;
        $this->stock = max(0, (float) $stock);
        $this->metadata = $metadata;
    }

    public function itemCode() { return $this->itemCode; }
    public function name() { return $this->name; }
    public function purchaseMultiple() { return $this->purchaseMultiple; }
    public function saleMultiple() { return $this->saleMultiple; }
    public function stock() { return $this->stock; }
    public function metadata() { return $this->metadata; }

    public function jsonSerialize()
    {
        return array_merge($this->metadata, array(
            'itemCode' => $this->itemCode,
            'nombre' => $this->name,
            'multiploCompra' => $this->purchaseMultiple,
            'multiploVenta' => $this->saleMultiple,
            'stock' => $this->stock,
        ));
    }
}
