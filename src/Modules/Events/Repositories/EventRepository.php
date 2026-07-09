<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Repositories;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Events\Domain\Event;
use AssociationManager\Modules\Events\Domain\EventStatus;

defined( 'ABSPATH' ) || exit;

final class EventRepository implements EventRepositoryInterface {

    public function find( int $id ): ?Event {
        global $wpdb;

        $table = DatabaseManager::table( 'events' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * @return Event[]
     */
    public function all(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'events' );

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY starts_at ASC", ARRAY_A );

        return array_map( fn ( array $row ): Event => $this->hydrate( $row ), $rows ?: [] );
    }

    /**
     * @return Event[]
     */
    public function upcomingPublished(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'events' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND starts_at >= %s ORDER BY starts_at ASC",
                EventStatus::PUBLISHED,
                current_time( 'mysql' )
            ),
            ARRAY_A
        );

        return array_map( fn ( array $row ): Event => $this->hydrate( $row ), $rows ?: [] );
    }

    public function paginate( PaginationParams $params ): PaginatedResult {
        global $wpdb;

        $table = DatabaseManager::table( 'events' );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY starts_at ASC LIMIT %d OFFSET %d",
                $params->limit(),
                $params->offset()
            ),
            ARRAY_A
        );

        $events = array_map( fn ( array $row ): Event => $this->hydrate( $row ), $rows ?: [] );

        return new PaginatedResult( $events, $total, $params->page, $params->perPage );
    }

    public function paginateUpcomingPublished( PaginationParams $params ): PaginatedResult {
        global $wpdb;

        $table = DatabaseManager::table( 'events' );
        $now   = current_time( 'mysql' );

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s AND starts_at >= %s",
                EventStatus::PUBLISHED,
                $now
            )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND starts_at >= %s ORDER BY starts_at ASC LIMIT %d OFFSET %d",
                EventStatus::PUBLISHED,
                $now,
                $params->limit(),
                $params->offset()
            ),
            ARRAY_A
        );

        $events = array_map( fn ( array $row ): Event => $this->hydrate( $row ), $rows ?: [] );

        return new PaginatedResult( $events, $total, $params->page, $params->perPage );
    }

    public function insert( Event $event ): int {
        global $wpdb;

        $table = DatabaseManager::table( 'events' );
        $now   = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            [
                'title'       => $event->title,
                'description' => $event->description,
                'location'    => $event->location,
                'starts_at'   => $event->startsAt,
                'ends_at'     => $event->endsAt,
                'capacity'    => $event->capacity,
                'status'      => $event->status,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function update( Event $event ): void {
        if ( $event->id === null ) {
            throw new \InvalidArgumentException( 'Cannot update an event without an id.' );
        }

        global $wpdb;

        $table = DatabaseManager::table( 'events' );

        $wpdb->update(
            $table,
            [
                'title'       => $event->title,
                'description' => $event->description,
                'location'    => $event->location,
                'starts_at'   => $event->startsAt,
                'ends_at'     => $event->endsAt,
                'capacity'    => $event->capacity,
                'status'      => $event->status,
                'updated_at'  => current_time( 'mysql' ),
            ],
            [ 'id' => $event->id ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): Event {
        return new Event(
            id: (int) $row['id'],
            title: $row['title'],
            description: $row['description'],
            location: $row['location'],
            startsAt: $row['starts_at'],
            endsAt: $row['ends_at'],
            capacity: $row['capacity'] !== null ? (int) $row['capacity'] : null,
            status: $row['status'],
        );
    }
}
