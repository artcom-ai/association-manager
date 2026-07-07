<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Services;

use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;

defined('ABSPATH') || exit;

final class MembershipExpiryCalculator
{
    public function __construct(
        private readonly MembershipPlanRegistry $plans
    ) {
    }

    /**
     * The effective moment a member actually stops being active: their
     * expires_at, pushed back by their plan's grace period (0 if no plan
     * is configured for their membership type).
     */
    public function cutoffFor(Member $member): ?string
    {
        if ($member->expiresAt === null) {
            return null;
        }

        $plan = $member->membershipType !== null ? $this->plans->get($member->membershipType) : null;
        $graceDays = $plan?->gracePeriodDays ?? 0;

        if ($graceDays <= 0) {
            return $member->expiresAt;
        }

        $timestamp = strtotime($member->expiresAt) + ($graceDays * DAY_IN_SECONDS);

        return date('Y-m-d H:i:s', $timestamp);
    }

    public function hasExpired(Member $member, string $now): bool
    {
        $cutoff = $this->cutoffFor($member);

        return $cutoff !== null && strtotime($cutoff) < strtotime($now);
    }
}
