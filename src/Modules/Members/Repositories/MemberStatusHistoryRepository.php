<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

use AssociationManager\Database\DatabaseManager;

defined('ABSPATH') || exit;

final class MemberStatusHistoryRepository implements MemberStatusHistoryRepositoryInterface
{
    public function record(
        int $memberId,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedBy,
        ?string $reason
    ): void {
        global $wpdb;

        $table = DatabaseManager::table('member_status_history');

        $wpdb->insert(
            $table,
            [
                'member_id' => $memberId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $changedBy,
                'reason' => $reason,
                'changed_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * @return array<int, array{from_status: ?string, to_status: string, changed_by: ?int, reason: ?string, changed_at: string}>
     */
    public function forMember(int $memberId): array
    {
        global $wpdb;

        $table = DatabaseManager::table('member_status_history');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE member_id = %d ORDER BY changed_at DESC, id DESC",
                $memberId
            ),
            ARRAY_A
        );

        return array_map(
            static fn (array $row): array => [
                'from_status' => $row['from_status'],
                'to_status' => $row['to_status'],
                'changed_by' => $row['changed_by'] !== null ? (int) $row['changed_by'] : null,
                'reason' => $row['reason'],
                'changed_at' => $row['changed_at'],
            ],
            $rows ?: []
        );
    }
}
