<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Domain;

defined('ABSPATH') || exit;

final class PaymentStatus
{
    public const PENDING = 'pending';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const REFUNDED = 'refunded';
}
