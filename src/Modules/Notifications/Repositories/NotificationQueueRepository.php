<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;

defined( 'ABSPATH' ) || exit;

final class NotificationQueueRepository implements NotificationQueueRepositoryInterface {

    public function enqueue( QueuedNotification $notification ): int {
        global $wpdb;

        $table = DatabaseManager::table( 'notification_queue' );

        $wpdb->insert(
            $table,
            [
                'event_key'    => $notification->eventKey,
                'channel'      => $notification->channel,
                'recipient'    => $notification->recipient,
                'subject'      => $notification->subject,
                'body'         => $notification->body,
                'status'       => $notification->status,
                'scheduled_at' => $notification->scheduledAt,
                'sent_at'      => $notification->sentAt,
                'attempts'     => $notification->attempts,
                'last_error'   => $notification->lastError,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * @return QueuedNotification[]
     */
    public function findDue( string $now ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'notification_queue' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND scheduled_at <= %s ORDER BY scheduled_at ASC",
                'pending',
                $now
            ),
            ARRAY_A
        );

        return array_map( fn ( array $row ): QueuedNotification => $this->hydrate( $row ), $rows ?: [] );
    }

    public function update( QueuedNotification $notification ): void {
        if ( $notification->id === null ) {
            throw new \InvalidArgumentException( 'Cannot update a queued notification without an id.' );
        }

        global $wpdb;

        $table = DatabaseManager::table( 'notification_queue' );

        $wpdb->update(
            $table,
            [
                'status'     => $notification->status,
                'sent_at'    => $notification->sentAt,
                'attempts'   => $notification->attempts,
                'last_error' => $notification->lastError,
            ],
            [ 'id' => $notification->id ],
            [ '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): QueuedNotification {
        return new QueuedNotification(
            id: (int) $row['id'],
            eventKey: $row['event_key'],
            channel: $row['channel'],
            recipient: $row['recipient'],
            subject: $row['subject'],
            body: $row['body'],
            status: $row['status'],
            scheduledAt: $row['scheduled_at'],
            sentAt: $row['sent_at'],
            attempts: (int) $row['attempts'],
            lastError: $row['last_error'],
        );
    }
}
