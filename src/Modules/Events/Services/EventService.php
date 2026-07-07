<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Services;

use AssociationManager\Modules\Events\Domain\Event;
use AssociationManager\Modules\Events\Repositories\EventRepositoryInterface;

defined('ABSPATH') || exit;

final class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $repository
    ) {
    }

    public function create(
        string $title,
        ?string $description,
        ?string $location,
        string $startsAt,
        ?string $endsAt,
        ?int $capacity
    ): Event {
        $id = $this->repository->insert(
            Event::draft($title, $description, $location, $startsAt, $endsAt, $capacity)
        );

        return $this->mustFind($id);
    }

    public function publish(int $eventId): Event
    {
        $published = $this->mustFind($eventId)->publish();

        $this->repository->update($published);

        return $published;
    }

    public function cancel(int $eventId): Event
    {
        $cancelled = $this->mustFind($eventId)->cancel();

        $this->repository->update($cancelled);

        return $cancelled;
    }

    public function find(int $id): ?Event
    {
        return $this->repository->find($id);
    }

    /**
     * @return Event[]
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return Event[]
     */
    public function upcomingPublished(): array
    {
        return $this->repository->upcomingPublished();
    }

    private function mustFind(int $id): Event
    {
        $event = $this->repository->find($id);

        if ($event === null) {
            throw new \RuntimeException("Event not found: {$id}");
        }

        return $event;
    }
}
