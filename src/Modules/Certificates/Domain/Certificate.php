<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Domain;

defined( 'ABSPATH' ) || exit;

final class Certificate {

    public function __construct(
        public readonly ?int $id,
        public readonly int $memberId,
        public readonly string $typeKey,
        public readonly int $wpAttachmentId,
        public readonly string $status,
        public readonly string $issuedAt,
        public readonly ?int $issuedBy,
    ) {
    }

    /**
     * A "draft" certificate already has its PDF generated and stored
     * (wpAttachmentId is never null through this class - see ADR-019's
     * addendum for why) - draft means "generated, not yet released to
     * the member", not "not yet built". $createdAt is a placeholder
     * timestamp here; issue() overwrites it with the real issuance
     * moment.
     */
    public static function draft( int $memberId, string $typeKey, int $wpAttachmentId, string $createdAt, ?int $createdBy ): self {
        return new self(
            id: null,
            memberId: $memberId,
            typeKey: $typeKey,
            wpAttachmentId: $wpAttachmentId,
            status: CertificateStatus::DRAFT,
            issuedAt: $createdAt,
            issuedBy: $createdBy,
        );
    }

    public function issue( string $issuedAt ): self {
        if ( $this->status !== CertificateStatus::DRAFT ) {
            throw new \LogicException( "Only a draft certificate can be issued (current status: \"{$this->status}\")." );
        }

        return new self(
            id: $this->id,
            memberId: $this->memberId,
            typeKey: $this->typeKey,
            wpAttachmentId: $this->wpAttachmentId,
            status: CertificateStatus::ISSUED,
            issuedAt: $issuedAt,
            issuedBy: $this->issuedBy,
        );
    }

    public function revoke(): self {
        if ( $this->status !== CertificateStatus::ISSUED ) {
            throw new \LogicException( "Only an issued certificate can be revoked (current status: \"{$this->status}\")." );
        }

        return new self(
            id: $this->id,
            memberId: $this->memberId,
            typeKey: $this->typeKey,
            wpAttachmentId: $this->wpAttachmentId,
            status: CertificateStatus::REVOKED,
            issuedAt: $this->issuedAt,
            issuedBy: $this->issuedBy,
        );
    }
}
