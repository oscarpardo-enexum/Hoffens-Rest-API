<?php

class Hoffensb2bOrdersModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        $page = max(1, (int) Tools::getValue('page', 1));
        $pageSize = 20;
        $error = null;
        $items = array();
        $pagination = array('page' => $page, 'totalPages' => 1, 'totalRecords' => 0);
        try {
            $cardCode = (new \Hoffens\B2B\Adapter\PrestaShop\CustomerCardCodeProvider())
                ->cardCodeForCustomer((int) $this->context->customer->id);
            if ($cardCode === null || trim($cardCode) === '') {
                throw new \Hoffens\B2B\Exception\IntegrationException('Su cuenta no tiene un cardCode asociado.');
            }
            $response = $this->api()->customerOrders($cardCode, $page, $pageSize);
            $items = isset($response['items']) && is_array($response['items']) ? $response['items'] : array();
            $pagination = isset($response['pagination']) && is_array($response['pagination'])
                ? $response['pagination'] : $pagination;
            foreach ($items as &$item) {
                $item['totalFormatted'] = Tools::displayPrice((float) $item['total'], $this->context->currency);
                $item['pendingFormatted'] = Tools::displayPrice((float) $item['totalPendienteDespacho'], $this->context->currency);
                $item['detailUrl'] = $this->context->link->getModuleLink(
                    'hoffensb2b', 'document', array('type' => 'NV', 'docEntry' => (int) $item['docEntry']), true
                );
            }
            unset($item);
        } catch (Exception $exception) {
            $error = 'No fue posible consultar sus pedidos en este momento.';
        }
        $this->context->smarty->assign(array(
            'hoffens_error' => $error,
            'hoffens_orders' => $items,
            'hoffens_pagination' => $pagination,
            'hoffens_prev_url' => $this->context->link->getModuleLink('hoffensb2b', 'orders', array('page' => max(1, $page - 1)), true),
            'hoffens_next_url' => $this->context->link->getModuleLink('hoffensb2b', 'orders', array('page' => $page + 1), true),
        ));
        $this->setTemplate('module:hoffensb2b/views/templates/front/orders.tpl');
    }

    private function api()
    {
        $config = (new \Hoffens\B2B\PrestaShop\PrestaShopConfigurationProvider())->get();
        return new \Hoffens\B2B\Adapter\Api\HoffensRestApi(
            new \Hoffens\B2B\Infrastructure\Http\HoffensApiClient(
                new \Hoffens\B2B\Infrastructure\Http\CurlTransport($config)
            )
        );
    }
}
