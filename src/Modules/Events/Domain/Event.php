<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Domain;

defined('ABSPATH') || exit;

final class Event
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $location,
        public readonly string $startsAt,
        public readonly ?string $endsAt,
        public readonly ?int $capacity,
        public readonly string $status,
    ) {
    }

    public static function draft(
        string $title,
        ?string $description,
        ?string $location,
        string $startsAt,
        ?string $endsAt,
        ?int $capacity
    ): self {
        return new self(
            id: null,
            title: $title,
            description: $description,
            location: $location,
            startsAt: $startsAt,
            endsAt: $endsAt,
            capacity: $capacity,
            status: EventStatus::DRAFT,
        );
    }

    public function publish(): self
    {
        if ($this->status === EventStatus::CANCELLED) {
            throw new \LogicException('Cannot publish a cancelled event.');
        }

        return new self(
            id: $this->id,
            title: $this->title,
            description: $this->description,
            location: $this->location,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            capacity: $this->capacity,
            status: EventStatus::PUBLISHED,
        );
    }

    public function cancel(): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            description: $this->description,
            location: $this->location,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            capacity: $this->capacity,
            status: EventStatus::CANCELLED,
        );
    }
}
