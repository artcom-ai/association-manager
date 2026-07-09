<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Domain;

use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationStatus;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;
use PHPUnit\Framework\TestCase;

final class QueuedNotificationTest extends TestCase
{
    private function draft(): QueuedNotification
    {
        return QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Welcome',
            'Body',
            '2026-01-01 00:00:00'
        );
    }

    public function testMarkReadSetsStatusAndPreservesEverythingElse(): void
    {
        $sent = $this->draft()->markSent('2026-01-01 01:00:00');

        $read = $sent->markRead();

        $this->assertSame(NotificationStatus::READ, $read->status);
        $this->assertSame($sent->id, $read->id);
        $this->assertSame($sent->sentAt, $read->sentAt);
        $this->assertSame($sent->attempts, $read->attempts);
        $this->assertSame($sent->lastError, $read->lastError);
    }
}
