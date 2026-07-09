<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Modules\Notifications\Services\NotificationService;
use AssociationManager\Tests\Support\TestCase;

final class NotificationServiceTest extends TestCase
{
    private NotificationQueueRepository $queue;
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new NotificationQueueRepository();
        $this->service = new NotificationService($this->queue);
    }

    public function testForRecipientReturnsThatRecipientsHistory(): void
    {
        $this->queue->enqueue(QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Welcome',
            'Body',
            '2026-01-01 00:00:00'
        ));

        $this->assertCount(1, $this->service->forRecipient('jane@example.test'));
        $this->assertSame([], $this->service->forRecipient('nobody@example.test'));
    }

    public function testMarkAllReadForRecipientOnlyTouchesSentNotifications(): void
    {
        $id = $this->queue->enqueue(QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Welcome',
            'Body',
            '2026-01-01 00:00:00'
        ));
        $queued = $this->queue->allForRecipient('jane@example.test')[0];
        $this->queue->update($queued->markSent('2026-01-01 01:00:00'));

        $pendingId = $this->queue->enqueue(QueuedNotification::draft(
            'certificate_issued',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Still pending',
            'Body',
            '2026-01-01 00:00:00'
        ));

        $this->service->markAllReadForRecipient('jane@example.test');

        $notifications = $this->service->forRecipient('jane@example.test');
        $byId = [];
        foreach ($notifications as $notification) {
            $byId[$notification->id] = $notification;
        }

        $this->assertSame('read', $byId[$id]->status);
        $this->assertSame('pending', $byId[$pendingId]->status);
    }
}
