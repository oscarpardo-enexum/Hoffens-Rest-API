<?php

class Hoffensb2bDocumentModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $error = null;
        $document = null;
        try {
            $type = strtoupper(trim((string) Tools::getValue('type')));
            $docEntry = (int) Tools::getValue('docEntry');
            $cardCode = (new \Hoffens\B2B\Adapter\PrestaShop\CustomerCardCodeProvider())
                ->cardCodeForCustomer((int) $this->context->customer->id);
            if ($cardCode === null || trim($cardCode) === '') {
                throw new \Hoffens\B2B\Exception\IntegrationException('Su cuenta no tiene un cardCode asociado.');
            }
            $document = $this->api()->document($type, $docEntry);
            if (!isset($document['cardCode']) || strcasecmp(trim((string) $document['cardCode']), trim($cardCode)) !== 0) {
                throw new \Hoffens\B2B\Exception\IntegrationException('El documento no pertenece al cliente autenticado.');
            }
            $document['totalFormatted'] = Tools::displayPrice((float) $document['total'], $this->context->currency);
            foreach ($document['items'] as &$item) {
                $item['unitPriceFormatted'] = Tools::displayPrice((float) $item['precioUnitario'], $this->context->currency);
                $item['lineTotalFormatted'] = Tools::displayPrice((float) $item['totalLinea'], $this->context->currency);
            }
            unset($item);
            if (!empty($document['digitalizacion']['url'])
                && strpos($document['digitalizacion']['url'], 'https://') !== 0) {
                $document['digitalizacion']['url'] = null;
                $document['digitalizacion']['disponible'] = false;
            }
        } catch (Exception $exception) {
            $error = 'No fue posible consultar el detalle solicitado.';
            $document = null;
        }
        $this->context->smarty->assign(array('hoffens_error' => $error, 'hoffens_document' => $document));
        $this->setTemplate('module:hoffensb2b/views/templates/front/document.tpl');
    }

    private function api()
    {
        $config = (new \Hoffens\B2B\PrestaShop\PrestaShopConfigurationProvider())->get();
        return new \Hoffens\B2B\Adapter\Api\HoffensRestApi(new \Hoffens\B2B\Infrastructure\Http\HoffensApiClient(
            new \Hoffens\B2B\Infrastructure\Http\CurlTransport($config)
        ));
    }
}
