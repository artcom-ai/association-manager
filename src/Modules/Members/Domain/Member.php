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
        public readonly ?string $sourceSystem = null,
        public readonly ?int $sourceUserId = null,
        public readonly ?string $importedAt = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {
    }

    /**
     * Joined first+last, trimmed - "" (not null) when neither part is
     * set, so callers can treat the empty-string case uniformly with
     * every other optional display field on this class (e.g. email/
     * memberNumber, which are rendered as "—" by the caller, not here).
     */
    public function fullName(): string {
        return trim( trim( (string) $this->firstName ) . ' ' . trim( (string) $this->lastName ) );
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

    public static function draft(
        ?int $wpUserId,
        ?string $membershipType,
        ?string $email = null,
        ?string $sourceSystem = null,
        ?int $sourceUserId = null,
        ?string $importedAt = null,
        ?string $firstName = null,
        ?string $lastName = null
    ): self {
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
            sourceSystem: $sourceSystem,
            sourceUserId: $sourceUserId,
            importedAt: $importedAt,
            firstName: $firstName,
            lastName: $lastName,
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
            sourceSystem: $this->sourceSystem,
            sourceUserId: $this->sourceUserId,
            importedAt: $this->importedAt,
            firstName: $this->firstName,
            lastName: $this->lastName,
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
            sourceSystem: $this->sourceSystem,
            sourceUserId: $this->sourceUserId,
            importedAt: $this->importedAt,
            firstName: $this->firstName,
            lastName: $this->lastName,
        );
    }

    /**
     * Records (or refreshes) where this member's data came from - set at
     * import creation time and re-stamped on every subsequent re-import
     * of the same source row, which is what makes re-running an importer
     * idempotent (MemberImportService looks members up by these two
     * fields before deciding to create vs. update).
     */
    public function withImportSource( string $sourceSystem, int $sourceUserId, string $importedAt ): self {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            wpUserId: $this->wpUserId,
            memberNumber: $this->memberNumber,
            email: $this->email,
            status: $this->status,
            membershipType: $this->membershipType,
            joinedAt: $this->joinedAt,
            expiresAt: $this->expiresAt,
            approvedAt: $this->approvedAt,
            sourceSystem: $sourceSystem,
            sourceUserId: $sourceUserId,
            importedAt: $importedAt,
            firstName: $this->firstName,
            lastName: $this->lastName,
        );
    }

    /**
     * Admin-editable identity fields, all three together - used both by
     * the Edit Member "Identity" section and by applyImport() refreshing
     * email/name from a re-run source. A null argument means "leave
     * unchanged" (not "clear"), matching applyImport()'s existing
     * only-overwrite-if-provided semantics for email.
     */
    public function withIdentity( ?string $email, ?string $firstName, ?string $lastName ): self {
        return new self(
            id: $this->id,
            uuid: $this->uuid,
            wpUserId: $this->wpUserId,
            memberNumber: $this->memberNumber,
            email: $email ?? $this->email,
            status: $this->status,
            membershipType: $this->membershipType,
            joinedAt: $this->joinedAt,
            expiresAt: $this->expiresAt,
            approvedAt: $this->approvedAt,
            sourceSystem: $this->sourceSystem,
            sourceUserId: $this->sourceUserId,
            importedAt: $this->importedAt,
            firstName: $firstName ?? $this->firstName,
            lastName: $lastName ?? $this->lastName,
        );
    }
}
