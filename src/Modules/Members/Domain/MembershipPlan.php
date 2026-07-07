<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined('ABSPATH') || exit;

final class MembershipPlan
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $durationDays,
        public readonly int $gracePeriodDays = 0,
    ) {
    }
}
