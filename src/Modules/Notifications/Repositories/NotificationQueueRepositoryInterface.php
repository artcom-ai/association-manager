<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Repositories;

use AssociationManager\Modules\Notifications\Domain\QueuedNotification;

defined( 'ABSPATH' ) || exit;

interface NotificationQueueRepositoryInterface {

    public function enqueue( QueuedNotification $notification ): int;

    /**
     * Pending rows due to be sent (scheduled_at <= $now).
     *
     * @return QueuedNotification[]
     */
    public function findDue( string $now ): array;

    public function update( QueuedNotification $notification ): void;

    /**
     * All notifications ever queued for a recipient email, newest first -
     * the Portal's member-facing notification list.
     *
     * @return QueuedNotification[]
     */
    public function allForRecipient( string $email ): array;
}
