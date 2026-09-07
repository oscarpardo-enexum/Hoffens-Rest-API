<?php

namespace Hoffens\B2B\Adapter\Notification;

use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\AlertNotifierInterface;

final class PrestaShopMailNotifier implements AlertNotifierInterface
{
    private $recipients;

    public function __construct(array $recipients)
    {
        $this->recipients = $recipients;
    }

    public function notify(array $incident)
    {
        if (count($this->recipients) === 0) {
            throw new IntegrationException('No existen destinatarios configurados para las alertas.');
        }
        $type = isset($incident['pending_notification']) ? $incident['pending_notification'] : 'failure';
        $recovered = $type === 'recovery';
        $subject = $recovered
            ? '[Hoffens B2B] Servicio recuperado'
            : '[Hoffens B2B] Incidencia de integración';
        $message = isset($incident['message']) ? (string) $incident['message'] : '';
        $variables = array(
            '{state}' => $recovered ? 'RECUPERADO' : 'FALLA',
            '{fingerprint}' => (string) $incident['fingerprint'],
            '{message_html}' => nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')),
            '{message_text}' => $message,
            '{occurrences}' => (int) $incident['occurrences'],
            '{last_seen_at}' => (string) $incident['last_seen_at'] . ' UTC',
        );
        foreach ($this->recipients as $recipient) {
            if (!\Mail::Send(
                (int) \Configuration::get('PS_LANG_DEFAULT'),
                'hoffens_alert',
                $subject,
                $variables,
                $recipient,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . 'hoffensb2b/mails/'
            )) {
                throw new IntegrationException('PrestaShop no pudo enviar la alerta a TI.');
            }
        }
    }
}
