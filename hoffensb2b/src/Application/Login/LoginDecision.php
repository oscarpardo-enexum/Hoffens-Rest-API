<?php

namespace Hoffens\B2B\Application\Login;

final class LoginDecision
{
    private $allowed;
    private $b2b;
    private $reason;
    private $snapshot;
    private $cacheHit;
    private $metrics;

    private function __construct($allowed, $b2b, $reason, array $snapshot, $cacheHit, array $metrics)
    {
        $this->allowed = (bool) $allowed;
        $this->b2b = (bool) $b2b;
        $this->reason = (string) $reason;
        $this->snapshot = $snapshot;
        $this->cacheHit = (bool) $cacheHit;
        $this->metrics = $metrics;
    }

    public static function observer($reason = 'b2c_observer', array $metrics = array())
    {
        return new self(true, false, $reason, array(), false, $metrics);
    }

    public static function allowed(array $snapshot, $cacheHit, array $metrics = array(), $isB2B = true)
    {
        return new self(
            true,
            $isB2B,
            $isB2B ? 'b2b_ready' : 'b2c_observed',
            $snapshot,
            $cacheHit,
            $metrics
        );
    }

    public static function denied($reason, array $metrics = array())
    {
        return new self(false, true, $reason, array(), false, $metrics);
    }

    public function isAllowed() { return $this->allowed; }
    public function isB2B() { return $this->b2b; }
    public function reason() { return $this->reason; }
    public function snapshot() { return $this->snapshot; }
    public function isCacheHit() { return $this->cacheHit; }
    public function metrics() { return $this->metrics; }
}
