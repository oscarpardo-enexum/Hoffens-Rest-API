<?php

namespace Hoffens\B2B\Adapter\Persistence;

use Hoffens\B2B\Exception\IntegrationException;
use Hoffens\B2B\Port\IncidentStoreInterface;

final class DbIncidentStore implements IncidentStoreInterface
{
    public function reportFailure($fingerprint, $message, $cooldownMinutes)
    {
        $fingerprint = $this->fingerprint($fingerprint);
        $cooldownMinutes = max(1, (int) $cooldownMinutes);
        $now = gmdate('Y-m-d H:i:s');
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'hoffens_b2b_incident` '
            . '(fingerprint,status,first_seen_at,last_seen_at,message,pending_notification,occurrences) VALUES ('
            . "'" . pSQL($fingerprint) . "','open','" . pSQL($now) . "','" . pSQL($now) . "','"
            . pSQL($this->message($message)) . "','failure',1) ON DUPLICATE KEY UPDATE "
            . "pending_notification=IF(status<>'open' OR last_notified_at IS NULL OR last_notified_at<"
            . "DATE_SUB(UTC_TIMESTAMP(), INTERVAL " . $cooldownMinutes . " MINUTE),'failure',pending_notification),"
            . "status='open',last_seen_at=VALUES(last_seen_at),recovered_at=NULL,message=VALUES(message),"
            . 'occurrences=occurrences+1';
        if (!\Db::getInstance()->execute($sql)) {
            throw new IntegrationException('No fue posible registrar la incidencia de integración.');
        }
    }

    public function reportRecovery($fingerprint, $message)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'hoffens_b2b_incident` SET '
            . "status='resolved',recovered_at='" . pSQL(gmdate('Y-m-d H:i:s')) . "',message='"
            . pSQL($this->message($message)) . "',pending_notification="
            . "IF(last_notified_at IS NULL,NULL,'recovery') WHERE fingerprint='"
            . pSQL($this->fingerprint($fingerprint)) . "' AND status='open'";
        if (!\Db::getInstance()->execute($sql)) {
            throw new IntegrationException('No fue posible cerrar la incidencia de integración.');
        }
    }

    public function pendingNotifications()
    {
        $rows = \Db::getInstance()->executeS('SELECT id_incident,fingerprint,status,first_seen_at,last_seen_at,'
            . 'recovered_at,message,pending_notification,occurrences FROM `'
            . _DB_PREFIX_ . "hoffens_b2b_incident` WHERE pending_notification IS NOT NULL "
            . 'ORDER BY id_incident ASC LIMIT 20');
        return is_array($rows) ? $rows : array();
    }

    public function markNotified($incidentId)
    {
        return \Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'hoffens_b2b_incident` SET '
            . "last_notified_at='" . pSQL(gmdate('Y-m-d H:i:s')) . "',pending_notification=NULL WHERE id_incident="
            . (int) $incidentId . ' AND pending_notification IS NOT NULL');
    }

    private function fingerprint($value)
    {
        $value = substr(trim((string) $value), 0, 190);
        if ($value === '') {
            throw new IntegrationException('La incidencia debe tener fingerprint.');
        }
        return $value;
    }

    private function message($value)
    {
        return substr(trim(preg_replace('/[\r\n]+/', ' ', (string) $value)), 0, 255);
    }
}
