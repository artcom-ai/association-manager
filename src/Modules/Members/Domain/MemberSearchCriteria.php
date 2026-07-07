<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined('ABSPATH') || exit;

final class MemberSearchCriteria
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $membershipType = null,
        public readonly ?string $search = null,
    ) {
    }
}
