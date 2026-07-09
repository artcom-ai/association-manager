<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Repositories;

use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Tests\Support\TestCase;

final class NotificationQueueRepositoryTest extends TestCase
{
    private NotificationQueueRepository $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new NotificationQueueRepository();
    }

    public function testAllForRecipientReturnsNewestFirstAndOnlyThatRecipient(): void
    {
        $this->queue->enqueue(QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'First',
            'Body',
            '2026-01-01 00:00:00'
        ));
        $this->queue->enqueue(QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'other@example.test',
            'Not hers',
            'Body',
            '2026-01-01 00:00:00'
        ));
        $this->queue->enqueue(QueuedNotification::draft(
            'certificate_issued',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Second',
            'Body',
            '2026-01-01 00:00:00'
        ));

        $notifications = $this->queue->allForRecipient('jane@example.test');

        $this->assertCount(2, $notifications);
        $this->assertSame('Second', $notifications[0]->subject);
        $this->assertSame('First', $notifications[1]->subject);
    }

    public function testAllForRecipientReturnsEmptyForUnknownRecipient(): void
    {
        $this->assertSame([], $this->queue->allForRecipient('nobody@example.test'));
    }
}
