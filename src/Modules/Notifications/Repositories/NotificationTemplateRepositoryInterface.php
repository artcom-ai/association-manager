<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Repositories;

use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;

defined('ABSPATH') || exit;

interface NotificationTemplateRepositoryInterface
{
    public function find(string $eventKey, string $channel): ?NotificationTemplate;

    /**
     * @return NotificationTemplate[]
     */
    public function all(): array;

    /**
     * Upsert keyed by (event_key, channel).
     */
    public function save(NotificationTemplate $template): void;
}
