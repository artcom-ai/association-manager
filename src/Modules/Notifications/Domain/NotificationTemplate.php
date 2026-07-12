<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Domain;

defined( 'ABSPATH' ) || exit;

final class NotificationTemplate {

    /**
     * See CertificateTemplate::$isDefault for the full reasoning - same
     * mechanism, same table-agnostic contract:
     * NotificationTemplateRepository::save() always persists
     * $isDefault = false regardless of what's passed here.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $eventKey,
        public readonly string $channel,
        public readonly string $subject,
        public readonly string $body,
        public readonly bool $isDefault = false,
    ) {
    }

    public function withContent( string $subject, string $body ): self {
        return new self(
            id: $this->id,
            eventKey: $this->eventKey,
            channel: $this->channel,
            subject: $subject,
            body: $body,
            isDefault: $this->isDefault,
        );
    }
}
