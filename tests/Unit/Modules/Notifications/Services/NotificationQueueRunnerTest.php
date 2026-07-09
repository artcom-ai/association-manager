<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Services;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationStatus;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Modules\Notifications\Services\EmailAdapter;
use AssociationManager\Modules\Notifications\Services\NotificationQueueRunner;
use AssociationManager\Tests\Support\TestCase;

final class NotificationQueueRunnerTest extends TestCase
{
    private NotificationQueueRepository $queue;
    private NotificationQueueRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new NotificationQueueRepository();
        $this->runner = new NotificationQueueRunner($this->queue, new EmailAdapter());

        $this->setNow('2026-01-01 12:00:00');
    }

    public function testSendsOnlyDueRowsAndMarksThemSent(): void
    {
        $due = QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Welcome',
            'Body',
            '2026-01-01 11:00:00'
        );
        $future = QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'later@example.test',
            'Welcome later',
            'Body later',
            '2026-02-01 00:00:00'
        );

        $this->queue->enqueue($due);
        $this->queue->enqueue($future);

        $sentCount = $this->runner->run();

        $this->assertSame(1, $sentCount);
        $this->assertCount(1, $this->sentMail());
        $this->assertSame('jane@example.test', $this->sentMail()[0]['to']);

        $stillPending = $this->queue->findDue('2026-02-01 00:00:00');
        $this->assertCount(1, $stillPending);
        $this->assertSame('later@example.test', $stillPending[0]->recipient);
    }

    public function testMarksFailedWithLastErrorWhenMailFails(): void
    {
        $this->failNextMail();

        $this->queue->enqueue(QueuedNotification::draft(
            'member_activated',
            NotificationChannel::EMAIL,
            'jane@example.test',
            'Welcome',
            'Body',
            '2026-01-01 11:00:00'
        ));

        $sentCount = $this->runner->run();

        $this->assertSame(0, $sentCount);

        $stillDue = $this->queue->findDue('2026-01-01 12:00:00');
        $this->assertCount(0, $stillDue, 'a failed row is no longer pending');

        $table = DatabaseManager::table('notification_queue');
        $rows = $this->wpdb->get_results("SELECT * FROM {$table}");
        $this->assertSame(NotificationStatus::FAILED, $rows[0]['status']);
        $this->assertSame('wp_mail() returned false', $rows[0]['last_error']);
    }
}
