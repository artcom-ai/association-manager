<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Services;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepositoryInterface;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepositoryInterface;

defined('ABSPATH') || exit;

final class MemberService
{
    public function __construct(
        private readonly MemberRepositoryInterface $repository,
        private readonly MemberStatusHistoryRepositoryInterface $history,
        private readonly MemberStatusRegistry $statuses,
        private readonly MembershipPlanRegistry $plans,
        private readonly MembershipRenewalRepositoryInterface $renewals,
    ) {
    }

    public function createMember(?int $wpUserId, ?string $membershipType): Member
    {
        $id = $this->repository->insert(Member::draft($wpUserId, $membershipType));

        $member = $this->mustFind($id);

        $this->history->record($member->id, null, $member->status, null, null);

        do_action('association_manager_member_status_changed', $member, null, $member->status);

        return $member;
    }

    public function activateMember(int $memberId, ?int $changedBy = null): Member
    {
        return $this->changeStatus($memberId, MemberStatus::ACTIVE, $changedBy);
    }

    public function suspendMember(int $memberId, ?int $changedBy = null, ?string $reason = null): Member
    {
        return $this->changeStatus($memberId, MemberStatus::SUSPENDED, $changedBy, $reason);
    }

    public function archiveMember(int $memberId, ?int $changedBy = null): Member
    {
        return $this->changeStatus($memberId, MemberStatus::INACTIVE, $changedBy);
    }

    public function expireMember(int $memberId, ?int $changedBy = null): Member
    {
        return $this->changeStatus($memberId, MemberStatus::EXPIRED, $changedBy);
    }

    /**
     * Manual renewal to an explicit date - unchanged from Sprint 9, but
     * now always recorded in the renewal history regardless of whether
     * the status actually changed.
     */
    public function renewMembership(int $memberId, string $newExpiresAt, ?int $changedBy = null): Member
    {
        $member = $this->mustFind($memberId);

        return $this->applyRenewal($member, null, $newExpiresAt, $changedBy);
    }

    /**
     * Renewal computed from the member's configured MembershipPlan:
     * extends from the later of "now" or their current expires_at (so
     * renewing early never loses remaining time), for the plan's
     * duration.
     */
    public function renewMembershipByPlan(int $memberId, ?int $changedBy = null): Member
    {
        $member = $this->mustFind($memberId);

        $plan = $member->membershipType !== null ? $this->plans->get($member->membershipType) : null;

        if ($plan === null) {
            throw new \LogicException(
                "No membership plan configured for type \"{$member->membershipType}\"."
            );
        }

        $now = strtotime(current_time('mysql'));
        $currentExpiry = $member->expiresAt !== null ? strtotime($member->expiresAt) : $now;
        $base = max($now, $currentExpiry);

        $newExpiresAt = date('Y-m-d H:i:s', $base + ($plan->durationDays * DAY_IN_SECONDS));

        return $this->applyRenewal($member, $plan->key, $newExpiresAt, $changedBy);
    }

    public function find(int $id): ?Member
    {
        return $this->repository->find($id);
    }

    /**
     * @return Member[]
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return PaginatedResult<Member>
     */
    public function paginate(PaginationParams $params): PaginatedResult
    {
        return $this->repository->paginate($params);
    }

    /**
     * @return PaginatedResult<Member>
     */
    public function search(MemberSearchCriteria $criteria, PaginationParams $params): PaginatedResult
    {
        return $this->repository->search($criteria, $params);
    }

    private function applyRenewal(
        Member $member,
        ?string $planKey,
        string $newExpiresAt,
        ?int $changedBy
    ): Member {
        $targetStatus = $member->status === MemberStatus::EXPIRED
            ? MemberStatus::ACTIVE
            : $member->status;

        if ($targetStatus !== $member->status && !$this->statuses->isTransitionAllowed($member->status, $targetStatus)) {
            throw new \LogicException(
                "Cannot renew a member with status \"{$member->status}\"."
            );
        }

        $renewed = $member
            ->withStatus($targetStatus)
            ->withExpiresAt($newExpiresAt);

        $this->repository->update($renewed);

        $this->renewals->record($member->id, $planKey, $member->expiresAt, $newExpiresAt, $changedBy);

        if ($targetStatus !== $member->status) {
            $this->history->record($member->id, $member->status, $targetStatus, $changedBy, 'renewal');
            do_action('association_manager_member_status_changed', $renewed, $member->status, $targetStatus);
        }

        do_action('association_manager_member_renewed', $renewed, $newExpiresAt);

        return $renewed;
    }

    private function changeStatus(
        int $memberId,
        string $newStatus,
        ?int $changedBy = null,
        ?string $reason = null
    ): Member {
        $member = $this->mustFind($memberId);

        if (!$this->statuses->isTransitionAllowed($member->status, $newStatus)) {
            throw new \LogicException(
                "Cannot transition member from \"{$member->status}\" to \"{$newStatus}\"."
            );
        }

        $approvedAt = ($newStatus === MemberStatus::ACTIVE && $member->approvedAt === null)
            ? current_time('mysql')
            : null;

        $updated = $member->withStatus($newStatus, $approvedAt);

        $this->repository->update($updated);

        $this->history->record($memberId, $member->status, $newStatus, $changedBy, $reason);

        do_action('association_manager_member_status_changed', $updated, $member->status, $newStatus);

        return $updated;
    }

    private function mustFind(int $id): Member
    {
        $member = $this->repository->find($id);

        if ($member === null) {
            throw new \RuntimeException("Member not found: {$id}");
        }

        return $member;
    }
}
