<?php

namespace Hoffens\B2B\Application\Login;

final class SessionRefreshPolicy
{
    private $idleSeconds;
    private $maxAgeSeconds;

    public function __construct($idleSeconds, $maxAgeSeconds)
    {
        $this->idleSeconds = max(300, (int) $idleSeconds);
        $this->maxAgeSeconds = max($this->idleSeconds, (int) $maxAgeSeconds);
    }

    public function shouldRefresh($now, $lastActivity, $lastSync, $nextAttempt)
    {
        $now = (int) $now;
        $lastActivity = max(0, (int) $lastActivity);
        $lastSync = max(0, (int) $lastSync);
        $nextAttempt = max(0, (int) $nextAttempt);

        if ($nextAttempt > $now) {
            return false;
        }
        if ($lastSync === 0 || ($now - $lastSync) >= $this->maxAgeSeconds) {
            return true;
        }
        return $lastActivity > 0 && ($now - $lastActivity) >= $this->idleSeconds;
    }
}
