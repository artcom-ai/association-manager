<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Events\Services;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Events\Domain\EventStatus;
use AssociationManager\Modules\Events\Repositories\EventRepository;
use AssociationManager\Modules\Events\Services\EventService;
use AssociationManager\Tests\Support\TestCase;

final class EventServiceTest extends TestCase
{
    private EventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventService(new EventRepository());
        $this->setNow('2026-06-01 00:00:00');
    }

    public function testCreateStartsAsDraft(): void
    {
        $event = $this->service->create('Annual Gala', null, 'Athens', '2027-01-01 10:00:00', null, 100);

        $this->assertSame(EventStatus::DRAFT, $event->status);
    }

    public function testDraftEventsAreNeverInUpcomingPublished(): void
    {
        $this->service->create('Future Draft', null, null, '2027-01-01 10:00:00', null, null);

        $this->assertCount(0, $this->service->upcomingPublished());
    }

    public function testPublishedFutureEventAppearsInUpcomingButPastOneDoesNot(): void
    {
        $past = $this->service->create('Past Meetup', null, null, '2020-01-01 10:00:00', null, null);
        $future = $this->service->create('Future Workshop', null, null, '2027-01-01 10:00:00', null, null);

        $this->service->publish($past->id);
        $this->service->publish($future->id);

        $upcoming = $this->service->upcomingPublished();

        $this->assertCount(1, $upcoming);
        $this->assertSame('Future Workshop', $upcoming[0]->title);
    }

    public function testCancelledEventDisappearsFromUpcoming(): void
    {
        $event = $this->service->create('Workshop', null, null, '2027-01-01 10:00:00', null, null);
        $this->service->publish($event->id);
        $this->service->cancel($event->id);

        $this->assertCount(0, $this->service->upcomingPublished());
    }

    public function testPublishingACancelledEventThrows(): void
    {
        $event = $this->service->create('Workshop', null, null, '2027-01-01 10:00:00', null, null);
        $this->service->cancel($event->id);

        $this->expectException(\LogicException::class);

        $this->service->publish($event->id);
    }

    public function testAdminPaginateIncludesAllStatuses(): void
    {
        $draft = $this->service->create('Draft Event', null, null, '2027-01-01 10:00:00', null, null);
        $published = $this->service->create('Published Event', null, null, '2027-02-01 10:00:00', null, null);
        $this->service->publish($published->id);

        $result = $this->service->paginate(new PaginationParams(1, 10));

        $this->assertSame(2, $result->total);
    }
}
