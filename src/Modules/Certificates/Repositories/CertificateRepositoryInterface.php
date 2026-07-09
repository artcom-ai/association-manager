<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Repositories;

use AssociationManager\Modules\Certificates\Domain\Certificate;

defined( 'ABSPATH' ) || exit;

interface CertificateRepositoryInterface {

    public function find( int $id ): ?Certificate;

    /**
     * @return Certificate[]
     */
    public function allForMember( int $memberId ): array;

    public function insert( Certificate $certificate ): int;
}
