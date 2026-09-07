<?php

namespace Hoffens\B2B\Application\Monitoring;

use Hoffens\B2B\Port\AlertNotifierInterface;
use Hoffens\B2B\Port\IncidentStoreInterface;

final class DispatchIncidentNotifications
{
    private $incidents;
    private $notifier;

    public function __construct(IncidentStoreInterface $incidents, AlertNotifierInterface $notifier)
    {
        $this->incidents = $incidents;
        $this->notifier = $notifier;
    }

    public function execute()
    {
        $sent = 0;
        $failed = 0;
        foreach ($this->incidents->pendingNotifications() as $incident) {
            try {
                $this->notifier->notify($incident);
                $this->incidents->markNotified((int) $incident['id_incident']);
                ++$sent;
            } catch (\Exception $exception) {
                ++$failed;
            }
        }
        return array('sent' => $sent, 'failed' => $failed);
    }
}
