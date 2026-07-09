<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined( 'ABSPATH' ) || exit;

final class Member {

    public function __construct(
        public readonly ?int $id,
        public readonly ?string $uuid,
        public readonly ?int $wpUserId,
        public readonly ?string $memberNumber,
        public readonly ?string $email,
        public readonly string $status,
        public readonly ?string $membershipType,
        public readonly ?string $joinedAt,
        public readonly ?string $expiresAt,
        public readonly ?string $approvedAt,
    ) {
    }

    /**
     * $id is nullable only to represent a not-yet-persisted draft (see
     * draft() below). Callers that already know they hold a persisted
     * member (e.g. one just loaded from or inserted into the
     * repository) use this instead of the raw, still-nullable $id
     * property.
     */
    public function requireId(): int {
        return $this->id ?? throw new \LogicException( 'Member has no id (not yet persisted).' );
    }

    public static function draft( ?int $wpUserId, ?string $membershipType, ?string $email = null ): self {
        return new self(
            id: null,
            uuid: null,
            wpUserId: $wpUserId,
            memberNumber: null,
            email: $email,
            status: MemberStatus::CANDIDATE,
            membershipType: $membershipType,
            joinedAt: null,
            expiresAt: null,
            approvedAt: null,
        );
    }

    /**
     * Pure state transition - no WordPress calls here. Callers (Service
     * layer) supply $approvedAt when the transition should stamp it.
     */
    public function withStatus( string $status, ?string $approvedAt = null ): self {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            wpUserId: $this->wpUserId,
            memberNumber: $this->memberNumber,
            email: $this->email,
            status: $status,
            membershipType: $this->membershipType,
            joinedAt: $this->joinedAt,
            expiresAt: $this->expiresAt,
            approvedAt: $approvedAt ?? $this->approvedAt,
        );
    }

    public function withExpiresAt( ?string $expiresAt ): self {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            wpUserId: $this->wpUserId,
            memberNumber: $this->memberNumber,
            email: $this->email,
            status: $this->status,
            membershipType: $this->membershipType,
            joinedAt: $this->joinedAt,
            expiresAt: $expiresAt,
            approvedAt: $this->approvedAt,
        );
    }
}
