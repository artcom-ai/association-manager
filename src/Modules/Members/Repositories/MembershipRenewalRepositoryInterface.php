<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

defined('ABSPATH') || exit;

interface MembershipRenewalRepositoryInterface
{
    public function record(
        int $memberId,
        ?string $planKey,
        ?string $previousExpiresAt,
        string $newExpiresAt,
        ?int $renewedBy
    ): void;

    /**
     * @return array<int, array{plan_key: ?string, previous_expires_at: ?string, new_expires_at: string, renewed_by: ?int, renewed_at: string}>
     */
    public function forMember(int $memberId): array;
}
