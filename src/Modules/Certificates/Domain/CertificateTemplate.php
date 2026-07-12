<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Domain;

defined( 'ABSPATH' ) || exit;

final class CertificateTemplate {

    /**
     * $isDefault distinguishes "this row still holds exactly what a
     * default-seeding migration inserted, untouched since" from "this
     * has been deliberately written since" (by an administrator via the
     * admin UI, or by an implementation plugin's own seeder) - without
     * inspecting the actual template text, which is unreliable (Core's
     * own default copy can change across releases, and there is no way
     * to distinguish an administrator's edit that happens to match
     * Core's default from one that doesn't). CertificateTemplateRepository::save()
     * always persists $isDefault = false regardless of what's passed
     * here, since any call to save() - by anyone - means "no longer an
     * untouched default." Only a default-seeding migration writing
     * directly (bypassing the repository) can produce a true row. See
     * docs/adr/024-elesyth-implementation-migrations.md's second addendum.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $typeKey,
        public readonly string $name,
        public readonly string $htmlBody,
        public readonly bool $isDefault = false,
    ) {
    }

    public function withContent( string $name, string $htmlBody ): self {
        return new self(
            id: $this->id,
            typeKey: $this->typeKey,
            name: $name,
            htmlBody: $htmlBody,
            isDefault: $this->isDefault,
        );
    }
}
