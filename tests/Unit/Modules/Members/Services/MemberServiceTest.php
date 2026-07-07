<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Services;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlan;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Tests\Support\TestCase;

final class MemberServiceTest extends TestCase
{
    private MemberService $service;
    private MemberStatusHistoryRepository $history;
    private MembershipRenewalRepository $renewals;
    private MembershipPlanRegistry $plans;

    protected function setUp(): void
    {
        parent::setUp();

        $this->history = new MemberStatusHistoryRepository();
        $this->renewals = new MembershipRenewalRepository();
        $this->plans = new MembershipPlanRegistry();

        $this->service = new MemberService(
            new MemberRepository(),
            $this->history,
            new MemberStatusRegistry(),
            $this->plans,
            $this->renewals,
        );
    }

    public function testCreateMemberStartsAsCandidateAndRecordsHistory(): void
    {
        $member = $this->service->createMember(10, 'individual');

        $this->assertSame(MemberStatus::CANDIDATE, $member->status);
        $this->assertNotNull($member->uuid);

        $history = $this->history->forMember($member->id);
        $this->assertCount(1, $history);
        $this->assertNull($history[0]['from_status']);
        $this->assertSame(MemberStatus::CANDIDATE, $history[0]['to_status']);
    }

    public function testActivateMemberSetsApprovedAtAndRecordsHistory(): void
    {
        $this->setNow('2026-03-01 10:00:00');
        $member = $this->service->createMember(null, 'individual');

        $activated = $this->service->activateMember($member->id, 7);

        $this->assertSame(MemberStatus::ACTIVE, $activated->status);
        $this->assertSame('2026-03-01 10:00:00', $activated->approvedAt);
        $this->assertCount(2, $this->history->forMember($member->id));
        // one firing from createMember() (null -> candidate), one from activateMember() (candidate -> active)
        $this->assertCount(2, $this->firedActionsNamed('association_manager_member_status_changed'));
    }

    public function testActivateSuspendReinstateArchiveLifecycle(): void
    {
        $member = $this->service->createMember(null, 'individual');
        $this->service->activateMember($member->id);

        $suspended = $this->service->suspendMember($member->id, 7, 'late payment');
        $this->assertSame(MemberStatus::SUSPENDED, $suspended->status);

        $reinstated = $this->service->activateMember($member->id, 7);
        $this->assertSame(MemberStatus::ACTIVE, $reinstated->status);

        $archived = $this->service->archiveMember($member->id, 7);
        $this->assertSame(MemberStatus::INACTIVE, $archived->status);
    }

    public function testTransitionStatusThrowsRuntimeExceptionWhenMemberNotFound(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->transitionStatus(999999, MemberStatus::ACTIVE);
    }

    public function testTransitionStatusThrowsLogicExceptionForInvalidTransition(): void
    {
        $member = $this->service->createMember(null, 'individual');

        $this->expectException(\LogicException::class);

        $this->service->transitionStatus($member->id, MemberStatus::SUSPENDED);
    }

    public function testTransitionStatusIsAGenericPublicEntryPoint(): void
    {
        $member = $this->service->createMember(null, 'individual');

        $activated = $this->service->transitionStatus($member->id, MemberStatus::ACTIVE, 7);

        $this->assertSame(MemberStatus::ACTIVE, $activated->status);
    }

    public function testUpdateMembershipTypeHasNoHistoryOrEventSideEffects(): void
    {
        $member = $this->service->createMember(null, 'individual');
        $eventsBefore = count($this->firedActions());
        $historyBefore = count($this->history->forMember($member->id));

        $updated = $this->service->updateMembershipType($member->id, 'family');

        $this->assertSame('family', $updated->membershipType);
        $this->assertCount($eventsBefore, $this->firedActions());
        $this->assertCount($historyBefore, $this->history->forMember($member->id));
    }

    public function testRenewMembershipAlwaysRecordsHistoryEvenWithoutStatusChange(): void
    {
        $member = $this->service->createMember(null, 'individual');
        $this->service->activateMember($member->id);

        $renewed = $this->service->renewMembership($member->id, '2027-06-01 00:00:00', 7);

        $this->assertSame(MemberStatus::ACTIVE, $renewed->status, 'renewing an active member must not change status');
        $this->assertSame('2027-06-01 00:00:00', $renewed->expiresAt);
        $this->assertCount(1, $this->renewals->forMember($member->id));
    }

    public function testRenewMembershipFlipsExpiredBackToActive(): void
    {
        $member = $this->service->createMember(null, 'individual');
        $this->service->activateMember($member->id);
        $this->service->transitionStatus($member->id, MemberStatus::EXPIRED);

        $renewed = $this->service->renewMembership($member->id, '2027-06-01 00:00:00', 7);

        $this->assertSame(MemberStatus::ACTIVE, $renewed->status);
    }

    public function testRenewMembershipByPlanThrowsWhenNoPlanConfigured(): void
    {
        $member = $this->service->createMember(null, 'no-such-plan');
        $this->service->activateMember($member->id);

        $this->expectException(\LogicException::class);

        $this->service->renewMembershipByPlan($member->id);
    }

    public function testRenewMembershipByPlanExtendsFromCurrentExpiryWhenNotYetExpired(): void
    {
        $this->plans->register(new MembershipPlan('annual', 'Annual', durationDays: 365, gracePeriodDays: 30));
        $this->setNow('2026-01-01 00:00:00');

        $member = $this->service->createMember(null, 'annual');
        $this->service->activateMember($member->id);
        $this->service->renewMembership($member->id, '2026-06-01 00:00:00');

        $renewed = $this->service->renewMembershipByPlan($member->id, 7);

        $this->assertSame('2027-06-01 00:00:00', $renewed->expiresAt, 'must extend from the existing future expiry, not from now');
        $this->assertSame(MemberStatus::ACTIVE, $renewed->status);
    }

    public function testRenewMembershipByPlanExtendsFromNowAndReactivatesWhenExpired(): void
    {
        $this->plans->register(new MembershipPlan('annual', 'Annual', durationDays: 365, gracePeriodDays: 30));
        $this->setNow('2026-01-01 00:00:00');

        $member = $this->service->createMember(null, 'annual');
        $this->service->activateMember($member->id);
        $this->service->transitionStatus($member->id, MemberStatus::EXPIRED);

        $renewed = $this->service->renewMembershipByPlan($member->id, 7);

        $this->assertSame('2027-01-01 00:00:00', $renewed->expiresAt);
        $this->assertSame(MemberStatus::ACTIVE, $renewed->status);
    }

    public function testImportRowCreatesWhenMemberNumberIsNew(): void
    {
        $result = $this->service->importRow('IMP-001', MemberStatus::ACTIVE, 'individual', '2026-01-01 00:00:00', '2027-01-01 00:00:00', 5);

        $this->assertSame('created', $result['action']);
        $this->assertSame(MemberStatus::ACTIVE, $result['member']->status);
    }

    public function testImportRowUpdatesWhenMemberNumberAlreadyExists(): void
    {
        $this->service->importRow('IMP-002', MemberStatus::ACTIVE, 'individual', null, null, 5);

        $result = $this->service->importRow('IMP-002', MemberStatus::SUSPENDED, null, null, null, 5);

        $this->assertSame('updated', $result['action']);
        $this->assertSame(MemberStatus::SUSPENDED, $result['member']->status);
    }

    public function testImportRowRejectsUnregisteredStatusString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->importRow('IMP-003', 'not-a-real-status', null, null, null, null);
    }

    public function testSearchFiltersByMemberNumber(): void
    {
        $this->service->importRow('FINDME', MemberStatus::ACTIVE, 'individual', null, null, null);
        $this->service->importRow('OTHER', MemberStatus::ACTIVE, 'individual', null, null, null);

        $result = $this->service->search(new MemberSearchCriteria(search: 'FINDME'), new PaginationParams(1, 10));

        $this->assertSame(1, $result->total);
        $this->assertSame('FINDME', $result->items[0]->memberNumber);
    }
}
