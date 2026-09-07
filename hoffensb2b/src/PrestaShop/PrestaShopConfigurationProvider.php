<?php

namespace Hoffens\B2B\PrestaShop;

use Hoffens\B2B\Configuration\ConfigKeys;
use Hoffens\B2B\Configuration\IntegrationConfig;

final class PrestaShopConfigurationProvider
{
    public function get()
    {
        $local = $this->localConfiguration();
        $baseUrl = isset($local['api_base_url'])
            ? $local['api_base_url']
            : \Configuration::get(ConfigKeys::BASE_URL);
        $token = getenv('HOFFENS_B2B_API_TOKEN');
        if ($token === false || $token === '') {
            $token = isset($local['api_token']) && $local['api_token'] !== ''
                ? $local['api_token']
                : \Configuration::get(ConfigKeys::TOKEN);
        }

        return new IntegrationConfig(
            $baseUrl,
            $token,
            \Configuration::get(ConfigKeys::CONNECT_TIMEOUT),
            \Configuration::get(ConfigKeys::TIMEOUT),
            \Configuration::get(ConfigKeys::RETRIES)
        );
    }

    private function localConfiguration()
    {
        $file = dirname(dirname(__DIR__)) . '/config/local.php';
        if (!is_file($file)) {
            return array();
        }
        $configuration = require $file;
        return is_array($configuration) ? $configuration : array();
    }
}
