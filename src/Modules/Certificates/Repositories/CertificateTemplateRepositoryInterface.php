<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Repositories;

use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;

defined( 'ABSPATH' ) || exit;

interface CertificateTemplateRepositoryInterface {

    public function find( string $typeKey ): ?CertificateTemplate;

    /**
     * @return CertificateTemplate[]
     */
    public function all(): array;

    /**
     * Upsert keyed by type_key.
     */
    public function save( CertificateTemplate $template ): void;
}
