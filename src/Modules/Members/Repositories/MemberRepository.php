<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;
use AssociationManager\Modules\Members\Domain\MemberStatus;

defined( 'ABSPATH' ) || exit;

final class MemberRepository implements MemberRepositoryInterface {

    public function find( int $id ): ?Member {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    public function findByMemberNumber( string $memberNumber ): ?Member {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE member_number = %s", $memberNumber ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    public function findByWpUserId( int $wpUserId ): ?Member {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d", $wpUserId ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * @return Member[]
     */
    public function all(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

        return array_map( fn ( array $row ): Member => $this->hydrate( $row ), $rows ?: [] );
    }

    public function paginate( PaginationParams $params ): PaginatedResult {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
                $params->limit(),
                $params->offset()
            ),
            ARRAY_A
        );

        $members = array_map( fn ( array $row ): Member => $this->hydrate( $row ), $rows ?: [] );

        return new PaginatedResult( $members, $total, $params->page, $params->perPage );
    }

    public function search( MemberSearchCriteria $criteria, PaginationParams $params ): PaginatedResult {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        [$where, $args] = $this->buildWhere( $criteria );

        // $where already carries its own %s/%d placeholders, paired 1:1 with $args and $selectArgs below;
        // the sniff can't see inside buildWhere()'s return value, hence the two ignores that follow.
        $total = (int) (
            $args === []
                ? $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" )
                : $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where}", ...$args ) ) // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        );

        $selectArgs = [ ...$args, $params->limit(), $params->offset() ];

        $rows = $wpdb->get_results(
            $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d",
                ...$selectArgs
            ),
            ARRAY_A
        );

        $members = array_map( fn ( array $row ): Member => $this->hydrate( $row ), $rows ?: [] );

        return new PaginatedResult( $members, $total, $params->page, $params->perPage );
    }

    /**
     * @return Member[]
     */
    public function findExpiredCandidates( string $now ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND expires_at IS NOT NULL AND expires_at < %s",
                MemberStatus::ACTIVE,
                $now
            ),
            ARRAY_A
        );

        return array_map( fn ( array $row ): Member => $this->hydrate( $row ), $rows ?: [] );
    }

    public function insert( Member $member ): int {
        global $wpdb;

        $table = DatabaseManager::table( 'members' );
        $now   = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            [
                'wp_user_id'      => $member->wpUserId,
                'uuid'            => wp_generate_uuid4(),
                'member_number'   => $member->memberNumber,
                'email'           => $member->email,
                'status'          => $member->status,
                'membership_type' => $member->membershipType,
                'joined_at'       => $member->joinedAt,
                'expires_at'      => $member->expiresAt,
                'approved_at'     => $member->approvedAt,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function update( Member $member ): void {
        if ( $member->id === null ) {
            throw new \InvalidArgumentException( 'Cannot update a member without an id.' );
        }

        global $wpdb;

        $table = DatabaseManager::table( 'members' );

        $wpdb->update(
            $table,
            [
                'wp_user_id'      => $member->wpUserId,
                'member_number'   => $member->memberNumber,
                'email'           => $member->email,
                'status'          => $member->status,
                'membership_type' => $member->membershipType,
                'joined_at'       => $member->joinedAt,
                'expires_at'      => $member->expiresAt,
                'approved_at'     => $member->approvedAt,
                'updated_at'      => current_time( 'mysql' ),
            ],
            [ 'id' => $member->id ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function buildWhere( MemberSearchCriteria $criteria ): array {
        global $wpdb;

        $clauses = [];
        $args    = [];

        if ( $criteria->status !== null ) {
            $clauses[] = 'status = %s';
            $args[]    = $criteria->status;
        }

        if ( $criteria->membershipType !== null ) {
            $clauses[] = 'membership_type = %s';
            $args[]    = $criteria->membershipType;
        }

        if ( $criteria->search !== null ) {
            $clauses[] = 'member_number LIKE %s';
            $args[]    = '%' . $wpdb->esc_like( $criteria->search ) . '%';
        }

        $where = $clauses === [] ? '' : ' WHERE ' . implode( ' AND ', $clauses );

        return [ $where, $args ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): Member {
        return new Member(
            id: (int) $row['id'],
            uuid: $row['uuid'],
            wpUserId: $row['wp_user_id'] !== null ? (int) $row['wp_user_id'] : null,
            memberNumber: $row['member_number'],
            email: $row['email'] ?? null,
            status: $row['status'],
            membershipType: $row['membership_type'],
            joinedAt: $row['joined_at'],
            expiresAt: $row['expires_at'],
            approvedAt: $row['approved_at'],
        );
    }
}
