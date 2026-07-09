<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

use AssociationManager\Database\DatabaseManager;

defined( 'ABSPATH' ) || exit;

final class MembershipRenewalRepository implements MembershipRenewalRepositoryInterface {

    public function record(
        int $memberId,
        ?string $planKey,
        ?string $previousExpiresAt,
        string $newExpiresAt,
        ?int $renewedBy
    ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'membership_renewals' );

        $wpdb->insert(
            $table,
            [
                'member_id'           => $memberId,
                'plan_key'            => $planKey,
                'previous_expires_at' => $previousExpiresAt,
                'new_expires_at'      => $newExpiresAt,
                'renewed_by'          => $renewedBy,
                'renewed_at'          => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * @return array<int, array{plan_key: ?string, previous_expires_at: ?string, new_expires_at: string, renewed_by: ?int, renewed_at: string}>
     */
    public function forMember( int $memberId ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'membership_renewals' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE member_id = %d ORDER BY renewed_at DESC, id DESC",
                $memberId
            ),
            ARRAY_A
        );

        return array_map(
            static fn ( array $row ): array => [
                'plan_key'            => $row['plan_key'],
                'previous_expires_at' => $row['previous_expires_at'],
                'new_expires_at'      => $row['new_expires_at'],
                'renewed_by'          => $row['renewed_by'] !== null ? (int) $row['renewed_by'] : null,
                'renewed_at'          => $row['renewed_at'],
            ],
            $rows ?: []
        );
    }
}
