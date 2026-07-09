<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Repositories;

use AssociationManager\Modules\Certificates\Domain\Certificate;

defined( 'ABSPATH' ) || exit;

interface CertificateRepositoryInterface {

    public function find( int $id ): ?Certificate;

    /**
     * Every status - callers that need to filter (e.g. member-facing
     * views wanting only "issued") do so at the Service layer.
     *
     * @return Certificate[]
     */
    public function allForMember( int $memberId ): array;

    /**
     * @return Certificate[]
     */
    public function all(): array;

    public function insert( Certificate $certificate ): int;

    public function update( Certificate $certificate ): void;
}
