<?php

class Hoffensb2bDocumentsModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $error = null;
        $documents = array();
        try {
            $cardCode = (new \Hoffens\B2B\Adapter\PrestaShop\CustomerCardCodeProvider())
                ->cardCodeForCustomer((int) $this->context->customer->id);
            if ($cardCode === null || trim($cardCode) === '') {
                throw new \Hoffens\B2B\Exception\IntegrationException('Su cuenta no tiene un cardCode asociado.');
            }
            $documents = $this->api()->customerDocuments($cardCode);
            foreach ($documents as &$document) {
                $type = $this->documentType($document);
                $document['totalFormatted'] = Tools::displayPrice((float) $document['total'], $this->context->currency);
                $document['balanceFormatted'] = Tools::displayPrice((float) $document['saldo'], $this->context->currency);
                $document['detailUrl'] = $this->context->link->getModuleLink(
                    'hoffensb2b', 'document', array('type' => $type, 'docEntry' => (int) $document['docEntry']), true
                );
            }
            unset($document);
        } catch (Exception $exception) {
            $error = 'No fue posible consultar sus documentos en este momento.';
        }
        $this->context->smarty->assign(array('hoffens_error' => $error, 'hoffens_documents' => $documents));
        $this->setTemplate('module:hoffensb2b/views/templates/front/documents.tpl');
    }

    private function documentType(array $document)
    {
        $type = isset($document['tipoDocumento']) ? strtoupper(trim((string) $document['tipoDocumento'])) : '';
        $map = array('13' => 'FC', '14' => 'NC', '18' => 'FC');
        if (in_array($type, array('NV', 'GD', 'FC', 'NC', 'ND'), true)) {
            return $type;
        }
        $objType = isset($document['objType']) ? (string) $document['objType'] : '';
        return isset($map[$objType]) ? $map[$objType] : 'FC';
    }

    private function api()
    {
        $config = (new \Hoffens\B2B\PrestaShop\PrestaShopConfigurationProvider())->get();
        return new \Hoffens\B2B\Adapter\Api\HoffensRestApi(new \Hoffens\B2B\Infrastructure\Http\HoffensApiClient(
            new \Hoffens\B2B\Infrastructure\Http\CurlTransport($config)
        ));
    }
}
