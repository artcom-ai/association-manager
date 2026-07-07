<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Domain;

defined('ABSPATH') || exit;

final class NotificationStatus
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
}
