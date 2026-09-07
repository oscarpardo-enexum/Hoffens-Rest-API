<?php

class Hoffensb2bCronModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax = true;
    public $maintenance = false;

    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $provided = (string) Tools::getValue('token');
        $expected = (string) Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::CRON_TOKEN);
        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            $this->ajaxRender(json_encode(array('ok' => false, 'error' => 'forbidden')));
            return;
        }
        try {
            $result = $this->module->runMaintenance();
            $this->ajaxRender(json_encode(array('ok' => true, 'result' => $result)));
        } catch (Exception $exception) {
            http_response_code(500);
            $this->ajaxRender(json_encode(array('ok' => false, 'error' => 'maintenance_failed')));
        }
    }
}
