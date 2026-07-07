<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Admin;

use AssociationManager\Modules\Members\Admin\MemberBulkActions;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Tests\Support\TestCase;

final class MemberBulkActionsTest extends TestCase
{
    private MemberService $service;
    private MemberBulkActions $bulkActions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MemberService(
            new MemberRepository(),
            new MemberStatusHistoryRepository(),
            new MemberStatusRegistry(),
            new MembershipPlanRegistry(),
            new MembershipRenewalRepository(),
        );

        $this->bulkActions = new MemberBulkActions($this->service);
    }

    public function testMixedValidAndInvalidIdsDoNotAbortTheBatch(): void
    {
        $a = $this->service->createMember(1, 'individual');
        $b = $this->service->createMember(2, 'individual');

        // both are still "candidate" - suspend is not a valid transition from there
        $result = $this->bulkActions->apply('suspend', [$a->id, $b->id, 999999], 5);

        $this->assertSame([], $result['succeeded']);
        $this->assertCount(3, $result['failed']);
    }

    public function testValidIdsSucceed(): void
    {
        $member = $this->service->createMember(1, 'individual');
        $this->service->activateMember($member->id);

        $result = $this->bulkActions->apply('suspend', [$member->id], 5);

        $this->assertSame([$member->id], $result['succeeded']);
        $this->assertSame([], $result['failed']);
    }

    public function testUnknownActionFailsGracefully(): void
    {
        $member = $this->service->createMember(1, 'individual');

        $result = $this->bulkActions->apply('delete-everything', [$member->id], 5);

        $this->assertSame([], $result['succeeded']);
        $this->assertArrayHasKey($member->id, $result['failed']);
    }
}
