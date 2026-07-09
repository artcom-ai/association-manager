<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Domain;

defined( 'ABSPATH' ) || exit;

final class CertificateTemplate {

    public function __construct(
        public readonly ?int $id,
        public readonly string $typeKey,
        public readonly string $name,
        public readonly string $htmlBody,
    ) {
    }

    public function withContent( string $name, string $htmlBody ): self {
        return new self(
            id: $this->id,
            typeKey: $this->typeKey,
            name: $name,
            htmlBody: $htmlBody,
        );
    }
}
