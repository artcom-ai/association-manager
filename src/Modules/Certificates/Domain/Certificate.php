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
        public readonly string $issuedAt,
        public readonly ?int $issuedBy,
    ) {
    }
}
