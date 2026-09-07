<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

return Db::getInstance()->execute(
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'hoffens_b2b_login_cache`, `'
    . _DB_PREFIX_ . 'hoffens_b2b_incident`, `'
    . _DB_PREFIX_ . 'hoffens_b2b_price_sync`, `'
    . _DB_PREFIX_ . 'hoffens_b2b_catalog`, `'
    . _DB_PREFIX_ . 'hoffens_b2b_catalog_state`, `'
    . _DB_PREFIX_ . 'hoffens_b2b_login_metric`, `'
    . _DB_PREFIX_ . 'hoffens_b2b_outbox`'
);
