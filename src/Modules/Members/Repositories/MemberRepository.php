<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Members\Domain\Member;

defined('ABSPATH') || exit;

final class MemberRepository implements MemberRepositoryInterface
{
    public function find(int $id): ?Member
    {
        global $wpdb;

        $table = DatabaseManager::table('members');

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return Member[]
     */
    public function all(): array
    {
        global $wpdb;

        $table = DatabaseManager::table('members');

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);

        return array_map(fn (array $row): Member => $this->hydrate($row), $rows ?: []);
    }

    public function insert(Member $member): int
    {
        global $wpdb;

        $table = DatabaseManager::table('members');
        $now = current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'wp_user_id' => $member->wpUserId,
                'member_number' => $member->memberNumber,
                'status' => $member->status,
                'membership_type' => $member->membershipType,
                'joined_at' => $member->joinedAt,
                'approved_at' => $member->approvedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(Member $member): void
    {
        if ($member->id === null) {
            throw new \InvalidArgumentException('Cannot update a member without an id.');
        }

        global $wpdb;

        $table = DatabaseManager::table('members');

        $wpdb->update(
            $table,
            [
                'member_number' => $member->memberNumber,
                'status' => $member->status,
                'membership_type' => $member->membershipType,
                'joined_at' => $member->joinedAt,
                'approved_at' => $member->approvedAt,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $member->id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function hydrate(array $row): Member
    {
        return new Member(
            id: (int) $row['id'],
            wpUserId: $row['wp_user_id'] !== null ? (int) $row['wp_user_id'] : null,
            memberNumber: $row['member_number'],
            status: $row['status'],
            membershipType: $row['membership_type'],
            joinedAt: $row['joined_at'],
            approvedAt: $row['approved_at'],
        );
    }
}
