<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Domain;

defined( 'ABSPATH' ) || exit;

final class NotificationTemplate {

    public function __construct(
        public readonly ?int $id,
        public readonly string $eventKey,
        public readonly string $channel,
        public readonly string $subject,
        public readonly string $body,
    ) {
    }

    public function withContent( string $subject, string $body ): self {
        return new self(
            id: $this->id,
            eventKey: $this->eventKey,
            channel: $this->channel,
            subject: $subject,
            body: $body,
        );
    }
}
