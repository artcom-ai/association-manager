<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The WP-Cron callback's body - same shape as MembershipExpiryRunner
 * (Sprint 11): fetch due work, process each item, persist the outcome.
 */
final class NotificationQueueRunner {

    public function __construct(
        private readonly NotificationQueueRepositoryInterface $queue,
        private readonly EmailAdapter $mailer,
    ) {
    }

    public function run(): int {
        $now = current_time( 'mysql' );
        $due = $this->queue->findDue( $now );

        $sent = 0;

        foreach ( $due as $notification ) {
            $result = $this->mailer->send( $notification->recipient, $notification->subject, $notification->body );

            if ( $result ) {
                $this->queue->update( $notification->markSent( current_time( 'mysql' ) ) );
                ++$sent;
            } else {
                $this->queue->update( $notification->markFailed( 'wp_mail() returned false' ) );
            }
        }

        return $sent;
    }
}
