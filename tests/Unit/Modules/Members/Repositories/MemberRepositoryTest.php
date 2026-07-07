<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Repositories;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Tests\Support\TestCase;

final class MemberRepositoryTest extends TestCase
{
    private MemberRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberRepository();
    }

    public function testInsertGeneratesAUuid(): void
    {
        $id = $this->repository->insert(Member::draft(1, 'individual'));

        $member = $this->repository->find($id);

        $this->assertNotNull($member->uuid);
    }

    public function testFindByMemberNumber(): void
    {
        $draft = Member::draft(null, 'individual');
        $id = $this->repository->insert($draft);
        $inserted = $this->repository->find($id);
        $this->repository->update(new Member(
            id: $inserted->id,
            uuid: $inserted->uuid,
            wpUserId: $inserted->wpUserId,
            memberNumber: 'M-100',
            email: $inserted->email,
            status: $inserted->status,
            membershipType: $inserted->membershipType,
            joinedAt: $inserted->joinedAt,
            expiresAt: $inserted->expiresAt,
            approvedAt: $inserted->approvedAt,
        ));

        $found = $this->repository->findByMemberNumber('M-100');
        $this->assertNotNull($found);
        $this->assertSame($id, $found->id);

        $this->assertNull($this->repository->findByMemberNumber('NO-SUCH-NUMBER'));
    }

    public function testPaginateReturnsCorrectTotalAndPageSlicing(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->repository->insert(Member::draft($i, 'individual'));
        }

        $page1 = $this->repository->paginate(new PaginationParams(1, 10));
        $page3 = $this->repository->paginate(new PaginationParams(3, 10));

        $this->assertSame(25, $page1->total);
        $this->assertCount(10, $page1->items);
        $this->assertCount(5, $page3->items);
    }

    public function testSearchFiltersByStatusAndMembershipType(): void
    {
        $activeId = $this->repository->insert(Member::draft(1, 'individual'));
        $active = $this->repository->find($activeId);
        $this->repository->update($active->withStatus(MemberStatus::ACTIVE));

        $this->repository->insert(Member::draft(2, 'family'));

        $result = $this->repository->search(new MemberSearchCriteria(status: MemberStatus::ACTIVE), new PaginationParams(1, 10));

        $this->assertSame(1, $result->total);
        $this->assertSame(MemberStatus::ACTIVE, $result->items[0]->status);
    }

    public function testFindExpiredCandidatesOnlyReturnsActiveMembersPastExpiry(): void
    {
        $this->setNow('2026-06-01 00:00:00');

        $pastId = $this->repository->insert(Member::draft(1, 'individual'));
        $past = $this->repository->find($pastId)->withStatus(MemberStatus::ACTIVE)->withExpiresAt('2026-01-01 00:00:00');
        $this->repository->update($past);

        $futureId = $this->repository->insert(Member::draft(2, 'individual'));
        $future = $this->repository->find($futureId)->withStatus(MemberStatus::ACTIVE)->withExpiresAt('2027-01-01 00:00:00');
        $this->repository->update($future);

        $candidates = $this->repository->findExpiredCandidates('2026-06-01 00:00:00');

        $this->assertCount(1, $candidates);
        $this->assertSame($pastId, $candidates[0]->id);
    }
}
