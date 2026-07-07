<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\QueuedNotification;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepositoryInterface;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepositoryInterface;

defined('ABSPATH') || exit;

final class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly NotificationQueueRepositoryInterface $queue,
        private readonly NotificationTemplateRenderer $renderer,
    ) {
    }

    /**
     * Looks up the template for $eventKey, renders it against
     * $placeholders, and enqueues it. Silently no-ops - does not throw -
     * when there's no recipient or no template configured for this
     * event key: both are expected, recoverable states (a member with
     * no resolvable email; a custom event key without a configured
     * template yet), not error conditions.
     *
     * @param array<string, string> $placeholders key => value, without braces
     */
    public function notify(
        string $eventKey,
        ?string $recipientEmail,
        array $placeholders,
        ?string $scheduledAt = null,
        string $channel = NotificationChannel::EMAIL
    ): void {
        if ($recipientEmail === null || $recipientEmail === '') {
            return;
        }

        $template = $this->templates->find($eventKey, $channel);

        if ($template === null) {
            return;
        }

        $notification = QueuedNotification::draft(
            eventKey: $eventKey,
            channel: $channel,
            recipient: $recipientEmail,
            subject: $this->renderer->render($template->subject, $placeholders),
            body: $this->renderer->render($template->body, $placeholders),
            scheduledAt: $scheduledAt ?? current_time('mysql'),
        );

        $this->queue->enqueue($notification);
    }
}
