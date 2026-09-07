<?php

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/hoffensb2b.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$provided = (string) Tools::getValue('token');
$expected = (string) Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::CRON_TOKEN);
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'forbidden'));
    exit;
}

try {
    $module = Module::getInstanceByName('hoffensb2b');
    if (!$module || !$module->active) {
        throw new RuntimeException('module_unavailable');
    }
    echo json_encode(array('ok' => true, 'result' => $module->runMaintenance()));
} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'maintenance_failed'));
}
