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

defined( 'ABSPATH' ) || exit;

final class MemberService {

    public function __construct(
        private readonly MemberRepositoryInterface $repository,
        private readonly MemberStatusHistoryRepositoryInterface $history,
        private readonly MemberStatusRegistry $statuses,
        private readonly MembershipPlanRegistry $plans,
        private readonly MembershipRenewalRepositoryInterface $renewals,
    ) {
    }

    public function createMember( ?int $wpUserId, ?string $membershipType, ?string $email = null ): Member {
        $id = $this->repository->insert( Member::draft( $wpUserId, $membershipType, $email ) );

        $member = $this->mustFind( $id );

        $this->history->record( $id, null, $member->status, null, null );

        do_action( 'association_manager_member_status_changed', $member, null, $member->status );

        return $member;
    }

    public function activateMember( int $memberId, ?int $changedBy = null ): Member {
        return $this->transitionStatus( $memberId, MemberStatus::ACTIVE, $changedBy );
    }

    public function suspendMember( int $memberId, ?int $changedBy = null, ?string $reason = null ): Member {
        return $this->transitionStatus( $memberId, MemberStatus::SUSPENDED, $changedBy, $reason );
    }

    public function archiveMember( int $memberId, ?int $changedBy = null ): Member {
        return $this->transitionStatus( $memberId, MemberStatus::INACTIVE, $changedBy );
    }

    public function expireMember( int $memberId, ?int $changedBy = null ): Member {
        return $this->transitionStatus( $memberId, MemberStatus::EXPIRED, $changedBy );
    }

    /**
     * Generic entry point for transitioning to any status registered in
     * MemberStatusRegistry (validated the same way as the named
     * convenience methods above) - used by Quick Edit, where the status
     * comes from a dropdown of all registered statuses rather than one
     * of the four fixed actions.
     */
    public function transitionStatus(
        int $memberId,
        string $newStatus,
        ?int $changedBy = null,
        ?string $reason = null
    ): Member {
        $member = $this->mustFind( $memberId );

        if ( ! $this->statuses->isTransitionAllowed( $member->status, $newStatus ) ) {
            throw new \LogicException(
                "Cannot transition member from \"{$member->status}\" to \"{$newStatus}\"."
            );
        }

        $approvedAt = ( $newStatus === MemberStatus::ACTIVE && $member->approvedAt === null )
            ? current_time( 'mysql' )
            : null;

        $updated = $member->withStatus( $newStatus, $approvedAt );

        $this->repository->update( $updated );

        $this->history->record( $memberId, $member->status, $newStatus, $changedBy, $reason );

        do_action( 'association_manager_member_status_changed', $updated, $member->status, $newStatus );

        return $updated;
    }

    /**
     * Plain field correction, not a lifecycle event: no transition
     * check, no history entry, no action hook.
     */
    public function updateMembershipType( int $memberId, ?string $membershipType ): Member {
        $member = $this->mustFind( $memberId );

        $updated = new Member(
            id: $member->id,
            uuid: $member->uuid,
            wpUserId: $member->wpUserId,
            memberNumber: $member->memberNumber,
            email: $member->email,
            status: $member->status,
            membershipType: $membershipType,
            joinedAt: $member->joinedAt,
            expiresAt: $member->expiresAt,
            approvedAt: $member->approvedAt,
        );

        $this->repository->update( $updated );

        return $updated;
    }

    /**
     * Links an existing member to a WP user account so they can access
     * the Member Portal - plain field correction, not a lifecycle
     * event, same reasoning as updateMembershipType(). Rejects linking
     * a WP account that's already linked to a different member (each
     * portal login must resolve to exactly one member).
     */
    public function linkWpUser( int $memberId, int $wpUserId ): Member {
        $member = $this->mustFind( $memberId );

        $existingLink = $this->repository->findByWpUserId( $wpUserId );

        if ( $existingLink !== null && $existingLink->requireId() !== $memberId ) {
            throw new \LogicException( "WP user {$wpUserId} is already linked to another member." );
        }

        $updated = new Member(
            id: $member->id,
            uuid: $member->uuid,
            wpUserId: $wpUserId,
            memberNumber: $member->memberNumber,
            email: $member->email,
            status: $member->status,
            membershipType: $member->membershipType,
            joinedAt: $member->joinedAt,
            expiresAt: $member->expiresAt,
            approvedAt: $member->approvedAt,
        );

        $this->repository->update( $updated );

        return $updated;
    }

    /**
     * CSV import row: create-or-update matched by member_number.
     * Deliberately bypasses the transition-graph check (a bulk data
     * load represents ground truth, not a business-rule-governed
     * transition) but still rejects a status string that isn't
     * registered at all. Records a history entry (reason: "import")
     * only when an existing member's status actually changes.
     *
     * @return array{action: 'created'|'updated', member: Member}
     */
    public function importRow(
        string $memberNumber,
        ?string $status,
        ?string $membershipType,
        ?string $joinedAt,
        ?string $expiresAt,
        ?int $importedBy = null,
        ?string $email = null
    ): array {
        if ( $status !== null && $this->statuses->get( $status ) === null ) {
            throw new \InvalidArgumentException( "Unknown status \"{$status}\"." );
        }

        $existing = $this->repository->findByMemberNumber( $memberNumber );

        if ( $existing === null ) {
            $draft = new Member(
                id: null,
                uuid: null,
                wpUserId: null,
                memberNumber: $memberNumber,
                email: $email,
                status: $status ?? MemberStatus::CANDIDATE,
                membershipType: $membershipType,
                joinedAt: $joinedAt,
                expiresAt: $expiresAt,
                approvedAt: null,
            );

            $id      = $this->repository->insert( $draft );
            $created = $this->mustFind( $id );

            $this->history->record( $id, null, $created->status, $importedBy, 'import' );

            return [
				'action' => 'created',
				'member' => $created,
			];
        }

        $updated = new Member(
            id: $existing->id,
            uuid: $existing->uuid,
            wpUserId: $existing->wpUserId,
            memberNumber: $memberNumber,
            email: $email ?? $existing->email,
            status: $status ?? $existing->status,
            membershipType: $membershipType ?? $existing->membershipType,
            joinedAt: $joinedAt ?? $existing->joinedAt,
            expiresAt: $expiresAt ?? $existing->expiresAt,
            approvedAt: $existing->approvedAt,
        );

        $this->repository->update( $updated );

        if ( $status !== null && $status !== $existing->status ) {
            $this->history->record( $existing->requireId(), $existing->status, $status, $importedBy, 'import' );
        }

        return [
			'action' => 'updated',
			'member' => $updated,
		];
    }

    /**
     * Manual renewal to an explicit date - unchanged from Sprint 9, but
     * now always recorded in the renewal history regardless of whether
     * the status actually changed.
     */
    public function renewMembership( int $memberId, string $newExpiresAt, ?int $changedBy = null ): Member {
        $member = $this->mustFind( $memberId );

        return $this->applyRenewal( $member, null, $newExpiresAt, $changedBy );
    }

    /**
     * Renewal computed from the member's configured MembershipPlan:
     * extends from the later of "now" or their current expires_at (so
     * renewing early never loses remaining time), for the plan's
     * duration.
     */
    public function renewMembershipByPlan( int $memberId, ?int $changedBy = null ): Member {
        $member = $this->mustFind( $memberId );

        $plan = $member->membershipType !== null ? $this->plans->get( $member->membershipType ) : null;

        if ( $plan === null ) {
            throw new \LogicException(
                "No membership plan configured for type \"{$member->membershipType}\"."
            );
        }

        $now           = strtotime( current_time( 'mysql' ) );
        $currentExpiry = $member->expiresAt !== null ? strtotime( $member->expiresAt ) : $now;
        $base          = max( $now, $currentExpiry );

        // date(), not gmdate(): $now/$currentExpiry are derived from current_time('mysql')'s
        // site-local convention, which every other stored timestamp in this codebase follows
        // (joined_at, approved_at, etc.) - gmdate() here would introduce a UTC/site-local mismatch.
        $newExpiresAt = date( 'Y-m-d H:i:s', $base + ( $plan->durationDays * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

        return $this->applyRenewal( $member, $plan->key, $newExpiresAt, $changedBy );
    }

    public function find( int $id ): ?Member {
        return $this->repository->find( $id );
    }

    public function findByWpUserId( int $wpUserId ): ?Member {
        return $this->repository->findByWpUserId( $wpUserId );
    }

    /**
     * Best-effort contact email for a member: their own stored email if
     * set, otherwise their linked WP account's email. Returns null if
     * neither is available (e.g. an unlinked member with no email on
     * file) - callers are expected to treat that as "cannot notify this
     * member" rather than an error.
     */
    public function resolveEmail( Member $member ): ?string {
        if ( $member->email !== null && $member->email !== '' ) {
            return $member->email;
        }

        if ( $member->wpUserId !== null ) {
            $user = get_userdata( $member->wpUserId );

            if ( $user !== false && ! empty( $user->user_email ) ) {
                return $user->user_email;
            }
        }

        return null;
    }

    /**
     * @return Member[]
     */
    public function all(): array {
        return $this->repository->all();
    }

    /**
     * @return PaginatedResult<Member>
     */
    public function paginate( PaginationParams $params ): PaginatedResult {
        return $this->repository->paginate( $params );
    }

    /**
     * @return PaginatedResult<Member>
     */
    public function search( MemberSearchCriteria $criteria, PaginationParams $params ): PaginatedResult {
        return $this->repository->search( $criteria, $params );
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

        if ( $targetStatus !== $member->status && ! $this->statuses->isTransitionAllowed( $member->status, $targetStatus ) ) {
            throw new \LogicException(
                "Cannot renew a member with status \"{$member->status}\"."
            );
        }

        $renewed = $member
            ->withStatus( $targetStatus )
            ->withExpiresAt( $newExpiresAt );

        $this->repository->update( $renewed );

        $this->renewals->record( $member->requireId(), $planKey, $member->expiresAt, $newExpiresAt, $changedBy );

        if ( $targetStatus !== $member->status ) {
            $this->history->record( $member->requireId(), $member->status, $targetStatus, $changedBy, 'renewal' );
            do_action( 'association_manager_member_status_changed', $renewed, $member->status, $targetStatus );
        }

        do_action( 'association_manager_member_renewed', $renewed, $newExpiresAt );

        return $renewed;
    }

    private function mustFind( int $id ): Member {
        $member = $this->repository->find( $id );

        if ( $member === null ) {
            throw new \RuntimeException( "Member not found: {$id}" );
        }

        return $member;
    }
}
