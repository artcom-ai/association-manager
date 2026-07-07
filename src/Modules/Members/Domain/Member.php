<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined('ABSPATH') || exit;

final class Member
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $wpUserId,
        public readonly ?string $memberNumber,
        public readonly string $status,
        public readonly ?string $membershipType,
        public readonly ?string $joinedAt,
        public readonly ?string $approvedAt,
    ) {
    }

    public static function draft(?int $wpUserId, ?string $membershipType): self
    {
        return new self(
            id: null,
            wpUserId: $wpUserId,
            memberNumber: null,
            status: MemberStatus::CANDIDATE,
            membershipType: $membershipType,
            joinedAt: null,
            approvedAt: null,
        );
    }

    public function approve(string $approvedAt): self
    {
        return new self(
            id: $this->id,
            wpUserId: $this->wpUserId,
            memberNumber: $this->memberNumber,
            status: MemberStatus::ACTIVE,
            membershipType: $this->membershipType,
            joinedAt: $this->joinedAt ?? $approvedAt,
            approvedAt: $approvedAt,
        );
    }

    public function suspend(): self
    {
        return new self(
            id: $this->id,
            wpUserId: $this->wpUserId,
            memberNumber: $this->memberNumber,
            status: MemberStatus::SUSPENDED,
            membershipType: $this->membershipType,
            joinedAt: $this->joinedAt,
            approvedAt: $this->approvedAt,
        );
    }
}
