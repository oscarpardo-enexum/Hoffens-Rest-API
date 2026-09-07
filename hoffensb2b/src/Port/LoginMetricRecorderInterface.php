<?php

namespace Hoffens\B2B\Port;

use Hoffens\B2B\Application\Login\LoginDecision;

interface LoginMetricRecorderInterface
{
    public function record($customerId, $mode, LoginDecision $decision, $totalMs);
}

