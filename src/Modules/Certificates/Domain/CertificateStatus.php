<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Domain;

defined( 'ABSPATH' ) || exit;

final class CertificateStatus {

    public const DRAFT   = 'draft';
    public const ISSUED  = 'issued';
    public const REVOKED = 'revoked';
}
