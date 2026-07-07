<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Repositories;

use AssociationManager\Modules\Events\Domain\Event;

defined('ABSPATH') || exit;

interface EventRepositoryInterface
{
    public function find(int $id): ?Event;

    /**
     * @return Event[]
     */
    public function all(): array;

    /**
     * Published events whose start time hasn't passed yet, soonest first.
     *
     * @return Event[]
     */
    public function upcomingPublished(): array;

    public function insert(Event $event): int;

    public function update(Event $event): void;
}
