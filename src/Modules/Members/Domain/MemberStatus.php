<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined('ABSPATH') || exit;

final class MemberStatus
{
    public const CANDIDATE = 'candidate';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const SUSPENDED = 'suspended';
    public const EXPIRED = 'expired';
    public const HONORARY = 'honorary';
}
