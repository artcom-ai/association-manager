<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;
use AssociationManager\Modules\Notifications\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Thin aggregator - owns no domain data of its own, only composes read
 * access to Members/Documents/Certificates/Notifications for the
 * self-service portal views. Depends on each module's own Service (not a
 * raw Repository interface - resolveEmail()/findByWpUserId() are
 * Service-layer concerns), same cross-module dependency pattern as
 * ADR-004 (Kernel::registerModules() registers Members, Documents,
 * Certificates, and Notifications before Portal).
 */
final class PortalService {

    private const MEMBER_ENTITY_TYPE = 'member';

    public function __construct(
        private readonly MemberService $members,
        private readonly DocumentService $documents,
        private readonly CertificateService $certificates,
        private readonly NotificationService $notifications,
        private readonly FieldRegistry $fieldRegistry,
        private readonly FieldValueService $fieldValueService,
    ) {
    }

    public function memberFor( int $wpUserId ): ?Member {
        return $this->members->findByWpUserId( $wpUserId );
    }

    /**
     * Every logged-in member sees the same private-tier documents -
     * there's no per-member ACL beyond the 3-tier visibility scheme in
     * this pass, so this doesn't need $member, just that the caller has
     * already confirmed one exists (i.e. the viewer is a real member).
     *
     * @return Document[]
     */
    public function visibleDocuments(): array {
        return $this->documents->listVisibleTo( Visibility::VISIBILITY_PRIVATE );
    }

    /**
     * @return Certificate[]
     */
    public function certificatesFor( Member $member ): array {
        return $this->certificates->issuedForMember( $member->requireId() );
    }

    /**
     * Notification history for this member, newest first. Returns an
     * empty list (rather than erroring) when the member has no
     * resolvable email - nothing could ever have been sent to them.
     *
     * @return QueuedNotification[]
     */
    public function notificationsFor( Member $member ): array {
        $email = $this->members->resolveEmail( $member );

        if ( $email === null ) {
            return [];
        }

        return $this->notifications->forRecipient( $email );
    }

    /**
     * Marks every sent-but-unread notification for this member as read -
     * called as a side effect of the member actually viewing the
     * notifications section, so the next render reflects accurate
     * "new" status without a separate explicit action.
     */
    public function markNotificationsReadFor( Member $member ): void {
        $email = $this->members->resolveEmail( $member );

        if ( $email === null ) {
            return;
        }

        $this->notifications->markAllReadForRecipient( $email );
    }

    /**
     * Any custom field registered against "member" (via FieldRegistry -
     * Core's own extension point, populated by a module or a per-client
     * implementation) that's visible at "private" level - the same rank
     * a logged-in member viewing their own Portal is granted elsewhere in
     * this class (see visibleDocuments()). Admin-only fields never
     * appear here. Same FieldRegistry + FieldValueService composition
     * Directory already uses (see DirectoryService::buildEntry()) - this
     * is the Portal gaining the same generic capability, not new logic.
     *
     * @return array<int, array{field: FieldDefinition, value: ?string}>
     */
    public function customFieldsFor( Member $member ): array {
        $values = $this->fieldValueService->valuesFor( self::MEMBER_ENTITY_TYPE, $member->requireId() );

        $rows = [];

        foreach ( $this->fieldRegistry->forEntityType( self::MEMBER_ENTITY_TYPE ) as $field ) {
            if ( ! $field->isVisibleTo( Visibility::VISIBILITY_PRIVATE ) ) {
                continue;
            }

            $rows[] = [
				'field' => $field,
				'value' => $values[ $field->key ] ?? null,
			];
        }

        return $rows;
    }

    /**
     * Profile editing is only available during onboarding - candidate
     * (registered, hasn't submitted yet) or pending_approval (submitted,
     * admin hasn't reviewed yet, still fixable). Once active, the Portal
     * reverts to read-only - this deliberately doesn't reopen the "no
     * full profile editing" decision for already-approved members.
     */
    public function canEditProfile( Member $member ): bool {
        return in_array( $member->status, [ MemberStatus::CANDIDATE, MemberStatus::PENDING_APPROVAL ], true );
    }

    /**
     * Saves the submitted custom field values (including any TYPE_FILE
     * uploads - see FieldValueService::saveWithUploads()), then
     * transitions candidate -> pending_approval (which fires the existing
     * member-status-changed hook, so NotificationsModule can alert the
     * admin - no separate notification wiring needed here). Idempotent on
     * the status: a member editing again while already pending_approval
     * just updates their values without a second transition
     * (transitionStatus() rejects a same-status transition outright).
     * Lets FieldValidationException propagate - the caller (the
     * admin-post handler) is expected to catch it and redisplay the form.
     *
     * @param array<string, mixed> $submittedValues
     * @param array{name?: mixed, type?: mixed, tmp_name?: mixed, error?: mixed, size?: mixed}|null $rawFileUploads the "custom_fields" sub-array of $_FILES
     */
    public function submitForApproval( Member $member, array $submittedValues, ?array $rawFileUploads = null ): Member {
        $this->fieldValueService->saveWithUploads( self::MEMBER_ENTITY_TYPE, $member->requireId(), $submittedValues, $rawFileUploads );

        if ( $member->status === MemberStatus::PENDING_APPROVAL ) {
            return $member;
        }

        return $this->members->transitionStatus( $member->requireId(), MemberStatus::PENDING_APPROVAL );
    }
}
