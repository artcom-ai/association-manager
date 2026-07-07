<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

defined('ABSPATH') || exit;

interface MemberStatusHistoryRepositoryInterface
{
    public function record(
        int $memberId,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedBy,
        ?string $reason
    ): void;

    /**
     * @return array<int, array{from_status: ?string, to_status: string, changed_by: ?int, reason: ?string, changed_at: string}>
     */
    public function forMember(int $memberId): array;
}
