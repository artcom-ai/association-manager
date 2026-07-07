<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Services;

use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined('ABSPATH') || exit;

/**
 * The WP-Cron callback's body: finds active members past their
 * grace-adjusted cutoff and expires them.
 */
final class MembershipExpiryRunner
{
    public function __construct(
        private readonly MemberRepositoryInterface $repository,
        private readonly MemberService $memberService,
        private readonly MembershipExpiryCalculator $calculator,
    ) {
    }

    public function run(): int
    {
        $now = current_time('mysql');
        $candidates = $this->repository->findExpiredCandidates($now);

        $expired = 0;

        foreach ($candidates as $member) {
            if ($this->calculator->hasExpired($member, $now)) {
                $this->memberService->expireMember($member->id);
                $expired++;
            }
        }

        return $expired;
    }
}
