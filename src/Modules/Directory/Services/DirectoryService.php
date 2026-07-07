<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory\Services;

use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined('ABSPATH') || exit;

final class DirectoryService
{
    public function __construct(
        private readonly MemberRepositoryInterface $members
    ) {
    }

    /**
     * Public-safe directory entries: active members only, no internal
     * identifiers (wp_user_id, id, status) exposed.
     *
     * @return array<int, array{member_number: ?string, membership_type: ?string, joined_at: ?string}>
     */
    public function listPublicEntries(): array
    {
        $entries = [];

        foreach ($this->members->all() as $member) {
            if ($member->status !== MemberStatus::ACTIVE) {
                continue;
            }

            $entries[] = [
                'member_number' => $member->memberNumber,
                'membership_type' => $member->membershipType,
                'joined_at' => $member->joinedAt,
            ];
        }

        return $entries;
    }
}
