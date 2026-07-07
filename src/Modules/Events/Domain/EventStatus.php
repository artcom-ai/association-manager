<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Domain;

defined('ABSPATH') || exit;

final class EventStatus
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const CANCELLED = 'cancelled';
}
