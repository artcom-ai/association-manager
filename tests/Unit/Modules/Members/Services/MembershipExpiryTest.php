<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Services;

use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlan;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Members\Services\MembershipExpiryCalculator;
use AssociationManager\Modules\Members\Services\MembershipExpiryRunner;
use AssociationManager\Tests\Support\TestCase;

final class MembershipExpiryTest extends TestCase
{
    private MemberService $service;
    private MembershipPlanRegistry $plans;
    private MembershipExpiryCalculator $calculator;
    private MembershipExpiryRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new MemberRepository();
        $this->plans = new MembershipPlanRegistry();

        $this->service = new MemberService(
            $repository,
            new MemberStatusHistoryRepository(),
            new MemberStatusRegistry(),
            $this->plans,
            new MembershipRenewalRepository(),
        );

        $this->calculator = new MembershipExpiryCalculator($this->plans);
        $this->runner = new MembershipExpiryRunner($repository, $this->service, $this->calculator);

        $this->plans->register(new MembershipPlan('annual', 'Annual', durationDays: 365, gracePeriodDays: 30));
        $this->setNow('2026-01-01 00:00:00');
    }

    public function testMemberWithinGracePeriodHasNotExpired(): void
    {
        $member = $this->service->createMember(null, 'annual');
        $this->service->activateMember($member->id);
        $tenDaysAgo = '2025-12-22 00:00:00';
        $this->service->renewMembership($member->id, $tenDaysAgo);

        $reloaded = $this->service->find($member->id);

        $this->assertFalse($this->calculator->hasExpired($reloaded, '2026-01-01 00:00:00'));
    }

    public function testMemberPastGracePeriodHasExpired(): void
    {
        $member = $this->service->createMember(null, 'annual');
        $this->service->activateMember($member->id);
        $thirtyOneDaysAgo = '2025-12-01 00:00:00';
        $this->service->renewMembership($member->id, $thirtyOneDaysAgo);

        $reloaded = $this->service->find($member->id);

        $this->assertTrue($this->calculator->hasExpired($reloaded, '2026-01-01 00:00:00'));
    }

    public function testRunnerOnlyExpiresMembersPastTheirGraceAdjustedCutoff(): void
    {
        // within grace - must stay active
        $graced = $this->service->createMember(null, 'annual');
        $this->service->activateMember($graced->id);
        $this->service->renewMembership($graced->id, '2025-12-22 00:00:00');

        // past grace, has a plan - must expire
        $expired = $this->service->createMember(null, 'annual');
        $this->service->activateMember($expired->id);
        $this->service->renewMembership($expired->id, '2025-12-01 00:00:00');

        // past raw expiry, no plan configured (0 grace) - must expire
        $noPlan = $this->service->createMember(null, 'no-such-plan');
        $this->service->activateMember($noPlan->id);
        $this->service->renewMembership($noPlan->id, '2025-12-22 00:00:00');

        $expiredCount = $this->runner->run();

        $this->assertSame(2, $expiredCount);
        $this->assertSame(MemberStatus::ACTIVE, $this->service->find($graced->id)->status);
        $this->assertSame(MemberStatus::EXPIRED, $this->service->find($expired->id)->status);
        $this->assertSame(MemberStatus::EXPIRED, $this->service->find($noPlan->id)->status);
    }
}
