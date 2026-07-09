<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Domain\NotificationStatus;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The member-facing read model over the notification queue - deliberately
 * decoupled from Members (works with a plain recipient email, not a
 * Member), so the Portal composes it the same way it composes Documents
 * and Certificates: via this module's own Service, not its Repository
 * directly.
 */
final class NotificationService {

    public function __construct(
        private readonly NotificationQueueRepositoryInterface $queue
    ) {
    }

    /**
     * @return \AssociationManager\Modules\Notifications\Domain\QueuedNotification[]
     */
    public function forRecipient( string $email ): array {
        return $this->queue->allForRecipient( $email );
    }

    /**
     * Marks every not-yet-read notification for this recipient as read.
     * Pending/failed notifications are left alone - "read" only makes
     * sense for something the member could actually have seen sent.
     */
    public function markAllReadForRecipient( string $email ): void {
        foreach ( $this->queue->allForRecipient( $email ) as $notification ) {
            if ( $notification->status === NotificationStatus::SENT ) {
                $this->queue->update( $notification->markRead() );
            }
        }
    }
}
