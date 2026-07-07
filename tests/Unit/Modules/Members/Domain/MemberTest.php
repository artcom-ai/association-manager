<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Domain;

use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Tests\Support\TestCase;

final class MemberTest extends TestCase
{
    public function testDraftStartsAsCandidateWithNoId(): void
    {
        $member = Member::draft(42, 'individual');

        $this->assertNull($member->id);
        $this->assertNull($member->uuid);
        $this->assertSame(42, $member->wpUserId);
        $this->assertSame(MemberStatus::CANDIDATE, $member->status);
        $this->assertSame('individual', $member->membershipType);
        $this->assertNull($member->approvedAt);
    }

    public function testWithStatusIsPureAndReturnsNewInstance(): void
    {
        $member = Member::draft(null, 'individual');

        $activated = $member->withStatus(MemberStatus::ACTIVE, '2026-01-01 00:00:00');

        $this->assertNotSame($member, $activated);
        $this->assertSame(MemberStatus::CANDIDATE, $member->status, 'original must be unmodified');
        $this->assertSame(MemberStatus::ACTIVE, $activated->status);
        $this->assertSame('2026-01-01 00:00:00', $activated->approvedAt);
    }

    public function testWithStatusWithoutApprovedAtKeepsExistingValue(): void
    {
        $member = Member::draft(null, null)->withStatus(MemberStatus::ACTIVE, '2026-01-01 00:00:00');

        $suspended = $member->withStatus(MemberStatus::SUSPENDED);

        $this->assertSame('2026-01-01 00:00:00', $suspended->approvedAt, 'approvedAt must not be lost on a later transition');
    }

    public function testWithExpiresAtIsPureAndOnlyChangesExpiry(): void
    {
        $member = Member::draft(null, 'individual');

        $renewed = $member->withExpiresAt('2027-01-01 00:00:00');

        $this->assertNull($member->expiresAt, 'original must be unmodified');
        $this->assertSame('2027-01-01 00:00:00', $renewed->expiresAt);
        $this->assertSame($member->status, $renewed->status);
    }
}
