<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepository;
use AssociationManager\Modules\Notifications\Services\NotificationDispatcher;
use AssociationManager\Modules\Notifications\Services\NotificationTemplateRenderer;
use AssociationManager\Tests\Support\TestCase;

final class NotificationDispatcherTest extends TestCase
{
    private NotificationTemplateRepository $templates;
    private NotificationQueueRepository $queue;
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templates = new NotificationTemplateRepository();
        $this->queue = new NotificationQueueRepository();

        $this->dispatcher = new NotificationDispatcher(
            $this->templates,
            $this->queue,
            new NotificationTemplateRenderer(),
        );

        $this->setNow('2026-01-01 00:00:00');
    }

    public function testEnqueuesRenderedNotificationWhenTemplateExists(): void
    {
        $this->templates->save(new NotificationTemplate(
            null,
            'member_activated',
            NotificationChannel::EMAIL,
            'Welcome {member_number}',
            'Hi {member_number}, you are now active.'
        ));

        $this->dispatcher->notify('member_activated', 'jane@example.test', ['member_number' => 'M-001']);

        $due = $this->queue->findDue('2026-01-01 00:00:00');
        $this->assertCount(1, $due);
        $this->assertSame('jane@example.test', $due[0]->recipient);
        $this->assertSame('Welcome M-001', $due[0]->subject);
        $this->assertSame('Hi M-001, you are now active.', $due[0]->body);
        $this->assertSame('2026-01-01 00:00:00', $due[0]->scheduledAt);
    }

    public function testNoOpsWhenTemplateIsMissing(): void
    {
        $this->dispatcher->notify('no_such_event', 'jane@example.test', []);

        $this->assertCount(0, $this->queue->findDue('2026-01-01 00:00:00'));
    }

    public function testNoOpsWhenRecipientIsNull(): void
    {
        $this->templates->save(new NotificationTemplate(
            null,
            'member_activated',
            NotificationChannel::EMAIL,
            'Welcome',
            'Body'
        ));

        $this->dispatcher->notify('member_activated', null, []);

        $this->assertCount(0, $this->queue->findDue('2026-01-01 00:00:00'));
    }

    public function testRespectsExplicitScheduledAt(): void
    {
        $this->templates->save(new NotificationTemplate(
            null,
            'member_activated',
            NotificationChannel::EMAIL,
            'Welcome',
            'Body'
        ));

        $this->dispatcher->notify('member_activated', 'jane@example.test', [], '2026-02-01 00:00:00');

        $this->assertCount(0, $this->queue->findDue('2026-01-01 00:00:00'));
        $this->assertCount(1, $this->queue->findDue('2026-02-01 00:00:00'));
    }
}
