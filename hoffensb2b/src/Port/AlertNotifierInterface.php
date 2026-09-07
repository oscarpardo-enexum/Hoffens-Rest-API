<?php

namespace Hoffens\B2B\Port;

interface AlertNotifierInterface
{
    public function notify(array $incident);
}
