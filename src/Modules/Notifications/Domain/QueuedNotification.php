<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Domain;

defined('ABSPATH') || exit;

final class QueuedNotification
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $eventKey,
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $status,
        public readonly string $scheduledAt,
        public readonly ?string $sentAt,
        public readonly int $attempts,
        public readonly ?string $lastError,
    ) {
    }

    public static function draft(
        string $eventKey,
        string $channel,
        string $recipient,
        string $subject,
        string $body,
        string $scheduledAt
    ): self {
        return new self(
            id: null,
            eventKey: $eventKey,
            channel: $channel,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            status: NotificationStatus::PENDING,
            scheduledAt: $scheduledAt,
            sentAt: null,
            attempts: 0,
            lastError: null,
        );
    }

    public function markSent(string $sentAt): self
    {
        return new self(
            id: $this->id,
            eventKey: $this->eventKey,
            channel: $this->channel,
            recipient: $this->recipient,
            subject: $this->subject,
            body: $this->body,
            status: NotificationStatus::SENT,
            scheduledAt: $this->scheduledAt,
            sentAt: $sentAt,
            attempts: $this->attempts + 1,
            lastError: null,
        );
    }

    public function markFailed(string $error): self
    {
        return new self(
            id: $this->id,
            eventKey: $this->eventKey,
            channel: $this->channel,
            recipient: $this->recipient,
            subject: $this->subject,
            body: $this->body,
            status: NotificationStatus::FAILED,
            scheduledAt: $this->scheduledAt,
            sentAt: $this->sentAt,
            attempts: $this->attempts + 1,
            lastError: $error,
        );
    }
}
