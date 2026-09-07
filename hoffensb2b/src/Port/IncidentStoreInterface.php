<?php

namespace Hoffens\B2B\Port;

interface IncidentStoreInterface
{
    public function reportFailure($fingerprint, $message, $cooldownMinutes);
    public function reportRecovery($fingerprint, $message);
    public function pendingNotifications();
    public function markNotified($incidentId);
}
