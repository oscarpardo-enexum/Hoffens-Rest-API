<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/autoload.php';
}

class HoffensB2B extends Module
{
    public function __construct()
    {
        $this->name = 'hoffensb2b';
        $this->tab = 'administration';
        $this->version = '0.8.0';
        $this->author = 'Enexum';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.8.0',
            'max' => '1.7.8.99',
        );

        parent::__construct();

        $this->displayName = $this->l('Hoffens B2B REST Integration');
        $this->description = $this->l('Integración desacoplada entre PrestaShop y la API B2B de Hoffens.');
    }

    public function install()
    {
        return parent::install()
            && $this->installSchema()
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::ENABLED, 0)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::MODE, 'disabled')
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::BASE_URL, 'https://apib2b.hoffens.com/api/v1/')
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::TOKEN, '')
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::B2B_GROUPS, '')
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::CONNECT_TIMEOUT, 2)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::TIMEOUT, 10)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::RETRIES, 2)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::PRICE_TTL, 0)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::ALERT_EMAILS, (string) Configuration::get('PS_SHOP_EMAIL'))
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::ALERT_COOLDOWN, 30)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::HEALTH_MAX_MS, 2000)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::METRIC_RETENTION_DAYS, 30)
            && Configuration::updateValue(\Hoffens\B2B\Configuration\ConfigKeys::CRON_TOKEN, Tools::passwdGen(40))
            && $this->registerHook('actionAuthentication')
            && $this->registerHook('displayCustomerLoginFormAfter')
            && $this->registerHook('displayCustomerAccount');
    }

    public function uninstall()
    {
        foreach (array(
            \Hoffens\B2B\Configuration\ConfigKeys::ENABLED,
            \Hoffens\B2B\Configuration\ConfigKeys::MODE,
            \Hoffens\B2B\Configuration\ConfigKeys::BASE_URL,
            \Hoffens\B2B\Configuration\ConfigKeys::TOKEN,
            \Hoffens\B2B\Configuration\ConfigKeys::B2B_GROUPS,
            \Hoffens\B2B\Configuration\ConfigKeys::CONNECT_TIMEOUT,
            \Hoffens\B2B\Configuration\ConfigKeys::TIMEOUT,
            \Hoffens\B2B\Configuration\ConfigKeys::RETRIES,
            \Hoffens\B2B\Configuration\ConfigKeys::PRICE_TTL,
            \Hoffens\B2B\Configuration\ConfigKeys::ALERT_EMAILS,
            \Hoffens\B2B\Configuration\ConfigKeys::ALERT_COOLDOWN,
            \Hoffens\B2B\Configuration\ConfigKeys::HEALTH_MAX_MS,
            \Hoffens\B2B\Configuration\ConfigKeys::METRIC_RETENTION_DAYS,
            \Hoffens\B2B\Configuration\ConfigKeys::CRON_TOKEN,
        ) as $key) {
            Configuration::deleteByName($key);
        }

        return $this->uninstallSchema() && parent::uninstall();
    }

    private function installSchema()
    {
        return (bool) require __DIR__ . '/sql/install.php';
    }

    private function uninstallSchema()
    {
        return (bool) require __DIR__ . '/sql/uninstall.php';
    }

    public function getContent()
    {
        $output = '';
        $endpointResult = null;
        if (Tools::isSubmit('submitHoffensB2B')) {
            $errors = $this->saveConfiguration();
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $output .= $this->displayError($error);
                }
            } else {
                $output .= $this->displayConfirmation($this->l('Configuración guardada.'));
            }
        }
        if (Tools::isSubmit('submitHoffensB2BTest')) {
            try {
                $startedAt = microtime(true);
                $health = $this->buildApi()->health();
                $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
                if (!isset($health['status']) || $health['status'] !== 'healthy') {
                    throw new Exception($this->l('La API respondió, pero su estado no es healthy.'));
                }
                $output .= $this->displayConfirmation(sprintf(
                    $this->l('Conexión correcta. API healthy en %d ms.'),
                    $elapsedMs
                ));
            } catch (Exception $exception) {
                $output .= $this->displayError($this->l('Falló la conexión REST: ') . $exception->getMessage());
            }
        }
        if (Tools::isSubmit('submitHoffensB2BEndpointTest')) {
            $endpointResult = (new \Hoffens\B2B\Application\Diagnostics\CheckEndpoint(
                $this->buildApi()
            ))->execute((string) Tools::getValue('HOFFENS_DIAGNOSTIC_ENDPOINT'), array(
                'cardCode' => (string) Tools::getValue('HOFFENS_DIAGNOSTIC_CARDCODE'),
                'documentType' => (string) Tools::getValue('HOFFENS_DIAGNOSTIC_DOCUMENT_TYPE'),
                'docEntry' => (int) Tools::getValue('HOFFENS_DIAGNOSTIC_DOC_ENTRY'),
            ));
        }
        if (Tools::isSubmit('submitHoffensB2BCatalogSync')) {
            try {
                $startedAt = microtime(true);
                $result = (new \Hoffens\B2B\Adapter\Persistence\DbCatalogSynchronizer())
                    ->synchronize($this->buildApi()->catalog());
                $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
                $output .= $this->displayConfirmation(sprintf(
                    $this->l('Catálogo sincronizado en %d ms: %d recibidos, %d vinculados, %d sin SKU local y %d múltiplos actualizados.%s'),
                    $elapsedMs,
                    $result['received'],
                    $result['mapped'],
                    $result['missing'],
                    $result['multiplesUpdated'],
                    $result['unchanged'] ? ' ' . $this->l('La fuente y el mapeo no presentaban cambios; no se realizaron escrituras.') : ''
                ));
            } catch (Exception $exception) {
                $output .= $this->displayError(
                    $this->l('Falló la sincronización del catálogo: ') . $exception->getMessage()
                );
            }
        }
        if (Tools::isSubmit('submitHoffensB2BMaintenance')) {
            try {
                $maintenance = $this->runMaintenance();
                $output .= $this->displayConfirmation(sprintf(
                    $this->l('Monitoreo ejecutado en %d ms. Estado: %s. Alertas enviadas: %d; pendientes con error: %d.'),
                    $maintenance['health']['elapsedMs'],
                    !$maintenance['health']['healthy']
                        ? $this->l('incidencia')
                        : (!empty($maintenance['health']['slow']) ? $this->l('saludable con latencia alta') : $this->l('saludable')),
                    $maintenance['notifications']['sent'],
                    $maintenance['notifications']['failed']
                ));
            } catch (Exception $exception) {
                $output .= $this->displayError($this->l('Falló el monitoreo: ') . $exception->getMessage());
            }
        }
        return $output
            . $this->renderConfigurationForm()
            . $this->renderMonitoringPanel()
            . $this->renderCatalogSyncPanel()
            . $this->renderEndpointDiagnostics($endpointResult)
            . $this->renderPerformancePanel();
    }

    public function hookActionAuthentication($params)
    {
        $mode = (string) Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::MODE);
        if ($mode === 'disabled' || empty($params['customer']) || !Validate::isLoadedObject($params['customer'])) {
            return;
        }

        $access = $this->buildAccessPolicy();
        if (!$access->shouldIntegrateLogin((int) $params['customer']->id)) {
            return;
        }

        $startedAt = microtime(true);
        try {
            $decision = $this->buildLoginUseCase($access)->execute((int) $params['customer']->id);
        } catch (Exception $exception) {
            $decision = \Hoffens\B2B\Application\Login\LoginDecision::denied('integration_unavailable');
        }

        try {
            $incidents = new \Hoffens\B2B\Adapter\Persistence\DbIncidentStore();
            if ($decision->reason() === 'integration_unavailable') {
                $incidents->reportFailure(
                    'login_integration',
                    'Los servicios requeridos durante el login no respondieron correctamente.',
                    (int) Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::ALERT_COOLDOWN)
                );
            } elseif ($decision->reason() === 'b2b_ready') {
                $incidents->reportRecovery('login_integration', 'La integración de login volvió a responder correctamente.');
            }
        } catch (Exception $exception) {
            // El registro de incidentes tampoco puede bloquear al cliente.
        }
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            (new \Hoffens\B2B\Adapter\Persistence\DbLoginMetricRecorder())->record(
                (int) $params['customer']->id,
                $mode,
                $decision,
                $elapsedMs
            );
        } catch (Exception $exception) {
            // La observabilidad nunca puede impedir el acceso del cliente.
        }

        // Evita una escritura SQL en cada login exitoso; se registran fallos y lentitud.
        if (!$decision->isAllowed() || $elapsedMs >= 2000) {
            PrestaShopLogger::addLog(
                sprintf('Hoffens B2B login: result=%s cache=%s duration_ms=%d',
                    $decision->reason(), $decision->isCacheHit() ? 'hit' : 'miss', $elapsedMs),
                $decision->isAllowed() ? 2 : 3,
                null,
                'Customer',
                (int) $params['customer']->id,
                true
            );
        }

        if ($mode === 'shadow' || $decision->isAllowed()) {
            return;
        }

        $this->context->customer->logout();
        $this->context->cookie->hoffens_b2b_login_error = $decision->reason();
        Tools::redirect($this->context->link->getPageLink('authentication', true));
    }

    public function hookDisplayCustomerLoginFormAfter()
    {
        if (empty($this->context->cookie->hoffens_b2b_login_error)) {
            return '';
        }
        unset($this->context->cookie->hoffens_b2b_login_error);
        return $this->display(__FILE__, 'views/templates/hook/login_error.tpl');
    }

    public function hookDisplayCustomerAccount()
    {
        $this->context->smarty->assign(array(
            'hoffens_orders_url' => $this->context->link->getModuleLink($this->name, 'orders', array(), true),
            'hoffens_documents_url' => $this->context->link->getModuleLink($this->name, 'documents', array(), true),
        ));
        return $this->display(__FILE__, 'views/templates/hook/customer_account.tpl');
    }

    private function buildLoginUseCase($access)
    {
        return new \Hoffens\B2B\Application\Login\PrepareCustomerSession(
            $access,
            new \Hoffens\B2B\Adapter\PrestaShop\CustomerCardCodeProvider(),
            $this->buildApi(),
            new \Hoffens\B2B\Adapter\Persistence\DbLoginSnapshotCache(),
            new \Hoffens\B2B\Adapter\Persistence\DbCustomerPriceSynchronizer(),
            (int) Configuration::get(\Hoffens\B2B\Configuration\ConfigKeys::PRICE_TTL)
        );
    }

    private function buildAccessPolicy()
    {
        $groupIds = array_filter(array_map('intval', explode(',', (string) Configuration::get(
            \Hoffens\B2B\Configuration\ConfigKeys::B2B_GROUPS
        ))));
        return new \Hoffens\B2B\Application\Access\B2BAccessPolicy(
            new \Hoffens\B2B\PrestaShop\CustomerGroupProvider(),
            $groupIds
        );
    }

    private function buildApi()
    {
        $config = (new \Hoffens\B2B\PrestaShop\PrestaShopConfigurationProvider())->get();
        $transport = new \Hoffens\B2B\Infrastructure\Http\CurlTransport($config);
        $client = new \Hoffens\B2B\Infrastructure\Http\HoffensApiClient($transport);
        return new \Hoffens\B2B\Adapter\Api\HoffensRestApi($client);
    }

    public function runMaintenance()
    {
        $keys = '\\Hoffens\\B2B\\Configuration\\ConfigKeys';
        $incidents = new \Hoffens\B2B\Adapter\Persistence\DbIncidentStore();
        $health = (new \Hoffens\B2B\Application\Monitoring\HealthMonitor(
            $this->buildApi(),
            $incidents,
            (int) Configuration::get($keys::HEALTH_MAX_MS),
            (int) Configuration::get($keys::ALERT_COOLDOWN)
        ))->execute();
        $notifications = (new \Hoffens\B2B\Application\Monitoring\DispatchIncidentNotifications(
            $incidents,
            new \Hoffens\B2B\Adapter\Notification\PrestaShopMailNotifier(
                $this->parseAlertEmails((string) Configuration::get($keys::ALERT_EMAILS))
            )
        ))->execute();
        $retentionDays = max(7, min(365, (int) Configuration::get($keys::METRIC_RETENTION_DAYS)));
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'hoffens_b2b_login_metric` '
            . 'WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $retentionDays . ' DAY)');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'hoffens_b2b_incident` '
            . "WHERE status='resolved' AND recovered_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL "
            . $retentionDays . ' DAY)');
        return array('health' => $health, 'notifications' => $notifications, 'retentionDays' => $retentionDays);
    }

    private function saveConfiguration()
    {
        $keys = '\\Hoffens\\B2B\\Configuration\\ConfigKeys';
        $baseUrl = rtrim(trim((string) Tools::getValue($keys::BASE_URL)), '/') . '/';
        $mode = (string) Tools::getValue($keys::MODE);
        $connectTimeout = (int) Tools::getValue($keys::CONNECT_TIMEOUT);
        $timeout = (int) Tools::getValue($keys::TIMEOUT);
        $priceTtl = (int) Tools::getValue($keys::PRICE_TTL);
        $retries = (int) Tools::getValue($keys::RETRIES);
        $alertEmails = trim((string) Tools::getValue($keys::ALERT_EMAILS));
        $alertCooldown = (int) Tools::getValue($keys::ALERT_COOLDOWN);
        $healthMaxMs = (int) Tools::getValue($keys::HEALTH_MAX_MS);
        $metricRetention = (int) Tools::getValue($keys::METRIC_RETENTION_DAYS);
        $groups = Tools::getValue($keys::B2B_GROUPS, array());
        $groups = is_array($groups) ? array_unique(array_filter(array_map('intval', $groups))) : array();

        $errors = array();
        if (strpos($baseUrl, 'https://') !== 0) {
            $errors[] = $this->l('La URL base debe utilizar HTTPS.');
        }
        if (!in_array($mode, array('disabled', 'shadow', 'rest'), true)) {
            $errors[] = $this->l('El modo de integración es inválido.');
        }
        if ($connectTimeout < 1 || $timeout < $connectTimeout) {
            $errors[] = $this->l('Los tiempos de espera son inválidos.');
        }
        if ($priceTtl < 0) {
            $errors[] = $this->l('La vigencia de precios no puede ser negativa.');
        }
        if ($retries < 0 || $retries > 3) {
            $errors[] = $this->l('Los reintentos deben estar entre 0 y 3.');
        }
        foreach ($this->parseAlertEmails($alertEmails) as $email) {
            if (!Validate::isEmail($email)) {
                $errors[] = sprintf($this->l('El correo de alerta "%s" no es válido.'), $email);
            }
        }
        if ($alertEmails === '') {
            $errors[] = $this->l('Debe configurar al menos un destinatario de alertas.');
        }
        if ($alertCooldown < 5 || $alertCooldown > 1440) {
            $errors[] = $this->l('La deduplicación de alertas debe estar entre 5 y 1440 minutos.');
        }
        if ($healthMaxMs < 100 || $healthMaxMs > 30000) {
            $errors[] = $this->l('El umbral del healthcheck debe estar entre 100 y 30000 ms.');
        }
        if ($metricRetention < 7 || $metricRetention > 365) {
            $errors[] = $this->l('La retención de métricas debe estar entre 7 y 365 días.');
        }
        if (count($errors) > 0) {
            return $errors;
        }

        Configuration::updateValue($keys::MODE, $mode);
        Configuration::updateValue($keys::ENABLED, $mode === 'rest' ? 1 : 0);
        Configuration::updateValue($keys::BASE_URL, $baseUrl);
        Configuration::updateValue($keys::B2B_GROUPS, implode(',', $groups));
        Configuration::updateValue($keys::CONNECT_TIMEOUT, $connectTimeout);
        Configuration::updateValue($keys::TIMEOUT, $timeout);
        Configuration::updateValue($keys::PRICE_TTL, $priceTtl);
        Configuration::updateValue($keys::RETRIES, $retries);
        Configuration::updateValue($keys::ALERT_EMAILS, implode(',', $this->parseAlertEmails($alertEmails)));
        Configuration::updateValue($keys::ALERT_COOLDOWN, $alertCooldown);
        Configuration::updateValue($keys::HEALTH_MAX_MS, $healthMaxMs);
        Configuration::updateValue($keys::METRIC_RETENTION_DAYS, $metricRetention);

        $newToken = trim((string) Tools::getValue($keys::TOKEN));
        if ($newToken !== '') {
            Configuration::updateValue($keys::TOKEN, $newToken);
        }
        return array();
    }

    private function renderConfigurationForm()
    {
        $keys = '\\Hoffens\\B2B\\Configuration\\ConfigKeys';
        $groups = Group::getGroups((int) $this->context->language->id);
        $selectedGroups = array_filter(array_map('intval', explode(',', (string) Configuration::get($keys::B2B_GROUPS))));

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitHoffensB2B';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value = array(
            $keys::MODE => Configuration::get($keys::MODE),
            $keys::BASE_URL => Configuration::get($keys::BASE_URL),
            $keys::TOKEN => '',
            $keys::B2B_GROUPS . '[]' => $selectedGroups,
            $keys::CONNECT_TIMEOUT => Configuration::get($keys::CONNECT_TIMEOUT),
            $keys::TIMEOUT => Configuration::get($keys::TIMEOUT),
            $keys::PRICE_TTL => Configuration::get($keys::PRICE_TTL),
            $keys::RETRIES => Configuration::get($keys::RETRIES),
            $keys::ALERT_EMAILS => Configuration::get($keys::ALERT_EMAILS),
            $keys::ALERT_COOLDOWN => Configuration::get($keys::ALERT_COOLDOWN),
            $keys::HEALTH_MAX_MS => Configuration::get($keys::HEALTH_MAX_MS),
            $keys::METRIC_RETENTION_DAYS => Configuration::get($keys::METRIC_RETENTION_DAYS),
        );

        return $helper->generateForm(array(array('form' => array(
            'legend' => array('title' => $this->l('Integración REST Hoffens')),
            'input' => array(
                array(
                    'type' => 'select', 'label' => $this->l('Modo'), 'name' => $keys::MODE,
                    'options' => array('query' => array(
                        array('id' => 'disabled', 'name' => $this->l('Desactivado')),
                        array('id' => 'shadow', 'name' => $this->l('Sombra (solo observar)')),
                        array('id' => 'rest', 'name' => $this->l('REST activo')),
                    ), 'id' => 'id', 'name' => 'name'),
                ),
                array('type' => 'text', 'label' => $this->l('URL base'), 'name' => $keys::BASE_URL, 'required' => true),
                array(
                    'type' => 'password', 'label' => $this->l('Bearer token'), 'name' => $keys::TOKEN,
                    'desc' => $this->l('Déjelo vacío para conservar el token actualmente configurado.'),
                ),
                array(
                    'type' => 'select', 'label' => $this->l('Grupos con permiso de compra B2B'), 'name' => $keys::B2B_GROUPS . '[]',
                    'multiple' => true,
                    'desc' => $this->l('No limita las mediciones: todos los clientes autenticados se registran.'),
                    'options' => array('query' => $groups, 'id' => 'id_group', 'name' => 'name'),
                ),
                array('type' => 'text', 'label' => $this->l('Timeout de conexión (s)'), 'name' => $keys::CONNECT_TIMEOUT),
                array('type' => 'text', 'label' => $this->l('Timeout total (s)'), 'name' => $keys::TIMEOUT),
                array('type' => 'text', 'label' => $this->l('Reintentos transitorios'), 'name' => $keys::RETRIES),
                array('type' => 'text', 'label' => $this->l('Vigencia caché de login (s)'), 'name' => $keys::PRICE_TTL),
                array('type' => 'text', 'label' => $this->l('Correos de alerta TI'), 'name' => $keys::ALERT_EMAILS,
                    'desc' => $this->l('Uno o más correos separados por coma.')),
                array('type' => 'text', 'label' => $this->l('Deduplicación de alertas (min)'), 'name' => $keys::ALERT_COOLDOWN),
                array('type' => 'text', 'label' => $this->l('Healthcheck máximo (ms)'), 'name' => $keys::HEALTH_MAX_MS),
                array('type' => 'text', 'label' => $this->l('Retención de métricas (días)'), 'name' => $keys::METRIC_RETENTION_DAYS),
            ),
            'submit' => array('title' => $this->l('Guardar')),
            'buttons' => array(array(
                'title' => $this->l('Probar conexión guardada'),
                'name' => 'submitHoffensB2BTest',
                'type' => 'submit',
                'class' => 'btn btn-default pull-right',
                'icon' => 'process-icon-refresh',
            )),
        ))));
    }

    private function renderEndpointDiagnostics($result)
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitHoffensB2BEndpointTest';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value = array(
            'HOFFENS_DIAGNOSTIC_ENDPOINT' => Tools::getValue('HOFFENS_DIAGNOSTIC_ENDPOINT', 'health'),
            'HOFFENS_DIAGNOSTIC_CARDCODE' => Tools::getValue('HOFFENS_DIAGNOSTIC_CARDCODE', ''),
            'HOFFENS_DIAGNOSTIC_DOCUMENT_TYPE' => Tools::getValue('HOFFENS_DIAGNOSTIC_DOCUMENT_TYPE', 'FC'),
            'HOFFENS_DIAGNOSTIC_DOC_ENTRY' => Tools::getValue('HOFFENS_DIAGNOSTIC_DOC_ENTRY', ''),
        );
        $form = array('form' => array(
            'legend' => array('title' => $this->l('Verificación de endpoints REST')),
            'description' => $this->l('Solo ejecuta consultas GET; no crea pedidos ni registra pagos.'),
            'input' => array(
                array(
                    'type' => 'select', 'label' => $this->l('Endpoint'), 'name' => 'HOFFENS_DIAGNOSTIC_ENDPOINT',
                    'options' => array('query' => array(
                        array('id' => 'health', 'name' => 'GET /health'),
                        array('id' => 'catalog', 'name' => 'GET /articulos'),
                        array('id' => 'customer', 'name' => 'GET /clientes/{cardCode}'),
                        array('id' => 'prices', 'name' => 'GET /clientes/{cardCode}/precios'),
                        array('id' => 'orders', 'name' => 'GET /clientes/{cardCode}/pedidos'),
                        array('id' => 'documents', 'name' => 'GET /clientes/{cardCode}/documentos'),
                        array('id' => 'document', 'name' => 'GET /documentos/{tipoDoc}/{docEntry}'),
                    ), 'id' => 'id', 'name' => 'name'),
                ),
                array('type' => 'text', 'label' => 'cardCode', 'name' => 'HOFFENS_DIAGNOSTIC_CARDCODE',
                    'desc' => $this->l('Requerido para los recursos asociados a un cliente.')),
                array(
                    'type' => 'select', 'label' => $this->l('Tipo documental'),
                    'name' => 'HOFFENS_DIAGNOSTIC_DOCUMENT_TYPE',
                    'options' => array('query' => array(
                        array('id' => 'NV', 'name' => 'NV'), array('id' => 'GD', 'name' => 'GD'),
                        array('id' => 'FC', 'name' => 'FC'), array('id' => 'NC', 'name' => 'NC'),
                        array('id' => 'ND', 'name' => 'ND'),
                    ), 'id' => 'id', 'name' => 'name'),
                ),
                array('type' => 'text', 'label' => 'DocEntry', 'name' => 'HOFFENS_DIAGNOSTIC_DOC_ENTRY'),
            ),
            'submit' => array('title' => $this->l('Ejecutar verificación'), 'icon' => 'process-icon-refresh'),
        ));
        $html = $helper->generateForm(array($form));
        if ($result === null) {
            return $html;
        }

        $class = $result->isSuccessful() ? 'alert-success' : 'alert-danger';
        $status = $result->statusCode() > 0 ? 'HTTP ' . $result->statusCode() : $this->l('Error de transporte');
        $html .= '<div class="alert ' . $class . '"><h4>'
            . Tools::safeOutput($result->endpoint()) . ' · ' . Tools::safeOutput($status)
            . ' · ' . (int) $result->elapsedMs() . ' ms</h4>';
        if ($result->isSuccessful()) {
            $html .= '<ul>';
            foreach ($result->summary() as $label => $value) {
                $html .= '<li><strong>' . Tools::safeOutput($label) . ':</strong> '
                    . Tools::safeOutput((string) $value) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . Tools::safeOutput($result->error()) . '</p>';
        }
        return $html . '</div>';
    }

    private function renderMonitoringPanel()
    {
        $keys = '\\Hoffens\\B2B\\Configuration\\ConfigKeys';
        $cronToken = (string) Configuration::get($keys::CRON_TOKEN);
        $cronUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__
            . 'modules/' . $this->name . '/cron.php?token=' . rawurlencode($cronToken);
        $html = '<div class="panel"><div class="panel-heading">'
            . $this->l('Monitoreo y alertas TI') . '</div><p>' . $this->l(
                'Ejecute la URL protegida cada 5 minutos. Comprueba API y base de datos, deduplica alertas, notifica recuperación y aplica la retención.'
            ) . '</p><div class="form-group"><label>' . $this->l('URL cron') . '</label>'
            . '<input class="form-control" type="text" readonly value="' . Tools::safeOutput($cronUrl) . '"></div>'
            . '<form method="post"><button type="submit" name="submitHoffensB2BMaintenance" class="btn btn-default">'
            . '<i class="process-icon-refresh"></i> ' . $this->l('Ejecutar monitoreo ahora')
            . '</button></form></div>';
        return $html;
    }

    private function renderCatalogSyncPanel()
    {
        $state = Db::getInstance()->getRow(
            'SELECT received_count, mapped_count, missing_count, synced_at FROM `'
            . _DB_PREFIX_ . 'hoffens_b2b_catalog_state` WHERE id_state = 1'
        );
        $html = '<div class="panel"><div class="panel-heading">'
            . $this->l('Catálogo maestro y múltiplos') . '</div>';
        $html .= '<p>' . $this->l(
            'Actualiza los múltiplos únicamente para productos y combinaciones que ya existen. Nunca crea productos.'
        ) . '</p>';
        if ($state) {
            $html .= '<p class="help-block">' . sprintf(
                $this->l('Última sincronización UTC: %s · %d recibidos · %d vinculados · %d sin SKU local.'),
                Tools::safeOutput($state['synced_at']),
                (int) $state['received_count'],
                (int) $state['mapped_count'],
                (int) $state['missing_count']
            ) . '</p>';
        } else {
            $html .= '<p class="help-block">' . $this->l('Todavía no se ha sincronizado el catálogo.') . '</p>';
        }
        $html .= '<form method="post"><button type="submit" name="submitHoffensB2BCatalogSync" '
            . 'class="btn btn-default"><i class="process-icon-refresh"></i> '
            . $this->l('Sincronizar catálogo y múltiplos') . '</button></form></div>';
        return $html;
    }

    private function renderPerformancePanel()
    {
        $table = _DB_PREFIX_ . 'hoffens_b2b_login_metric';
        $rows = Db::getInstance()->executeS(
            'SELECT m.*, c.firstname, c.lastname
             FROM `' . bqSQL($table) . '` m
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = m.id_customer
             ORDER BY m.id_metric DESC LIMIT 50'
        );
        $summary = Db::getInstance()->getRow(
            'SELECT COUNT(*) AS total,
                    ROUND(AVG(total_ms)) AS average_ms,
                    SUM(result <> "b2b_ready") AS failures,
                    SUM(cache_hit = 1) AS cache_hits
             FROM `' . bqSQL($table) . '`
             WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)'
        );

        $html = '<div class="panel"><div class="panel-heading">'
            . $this->l('Rendimiento de integraciones en login (últimas 24 horas)') . '</div>';
        $html .= '<div class="row text-center">'
            . $this->metricBox($this->l('Logins autenticados'), isset($summary['total']) ? $summary['total'] : 0)
            . $this->metricBox($this->l('Promedio total'), (isset($summary['average_ms']) ? $summary['average_ms'] : 0) . ' ms')
            . $this->metricBox($this->l('Fallos'), isset($summary['failures']) ? $summary['failures'] : 0)
            . $this->metricBox($this->l('Caché'), isset($summary['cache_hits']) ? $summary['cache_hits'] : 0)
            . '</div>';
        $html .= '<p class="help-block">' . $this->l(
            'Se registran tiempos y tamaños, nunca payloads, precios ni tokens. Perfil y precios se ejecutan en paralelo.'
        ) . '</p>';
        $html .= '<div class="table-responsive"><table class="table table-striped table-condensed">'
            . '<thead><tr><th>' . $this->l('Fecha UTC') . '</th><th>' . $this->l('Cliente') . '</th>'
            . '<th>' . $this->l('Modo') . '</th><th>' . $this->l('Resultado') . '</th>'
            . '<th>' . $this->l('Total') . '</th><th>' . $this->l('API paralela') . '</th><th>' . $this->l('Perfil') . '</th>'
            . '<th>' . $this->l('Precios') . '</th><th>' . $this->l('Sincronización') . '</th>'
            . '<th>' . $this->l('Recibidos') . '</th><th>' . $this->l('Escritos') . '</th><th>' . $this->l('Estado precios') . '</th><th>' . $this->l('Sin SKU') . '</th>'
            . '<th>' . $this->l('Tamaño precios') . '</th><th>' . $this->l('Caché') . '</th></tr></thead><tbody>';

        if (!is_array($rows) || count($rows) === 0) {
            $html .= '<tr><td colspan="15" class="text-center">' . $this->l('Todavía no existen mediciones.') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                $customer = trim($row['firstname'] . ' ' . $row['lastname']);
                $customer = '#' . (int) $row['id_customer'] . ($customer !== '' ? ' · ' . $customer : '');
                $html .= '<tr><td>' . Tools::safeOutput($row['created_at']) . '</td>'
                    . '<td>' . Tools::safeOutput($customer) . '</td>'
                    . '<td>' . Tools::safeOutput($row['mode']) . '</td>'
                    . '<td>' . Tools::safeOutput($row['result']) . '</td>'
                    . '<td><strong>' . (int) $row['total_ms'] . ' ms</strong></td>'
                    . '<td>' . $this->optionalMs($row['api_batch_ms']) . '</td>'
                    . '<td>' . $this->optionalMs($row['profile_ms']) . '</td>'
                    . '<td>' . $this->optionalMs($row['prices_ms']) . '</td>'
                    . '<td>' . $this->optionalMs($row['price_sync_ms']) . '</td>'
                    . '<td>' . (int) $row['price_count'] . '</td>'
                    . '<td>' . (int) $row['price_written'] . '</td>'
                    . '<td>' . ((int) $row['price_unchanged'] === 1 ? $this->l('Sin cambios') : $this->l('Actualizado')) . '</td>'
                    . '<td>' . (int) $row['price_unmatched'] . '</td>'
                    . '<td>' . $this->formatBytes((int) $row['prices_bytes']) . '</td>'
                    . '<td>' . ((int) $row['cache_hit'] === 1 ? $this->l('Sí') : $this->l('No')) . '</td></tr>';
            }
        }
        return $html . '</tbody></table></div></div>';
    }

    private function metricBox($label, $value)
    {
        return '<div class="col-sm-3"><div style="font-size:22px;font-weight:600">'
            . Tools::safeOutput((string) $value) . '</div><div class="text-muted">'
            . Tools::safeOutput($label) . '</div></div>';
    }

    private function optionalMs($value)
    {
        return $value === null ? '—' : (int) $value . ' ms';
    }

    private function formatBytes($bytes)
    {
        if ($bytes <= 0) {
            return '—';
        }
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 2, ',', '.') . ' MB'
            : number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }

    private function parseAlertEmails($value)
    {
        $emails = preg_split('/[,;\s]+/', trim((string) $value));
        return array_values(array_unique(array_filter(array_map('trim', $emails))));
    }
}
