<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Services;

use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\Member;
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

    public function __construct(
        private readonly MemberService $members,
        private readonly DocumentService $documents,
        private readonly CertificateService $certificates,
        private readonly NotificationService $notifications,
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
}
