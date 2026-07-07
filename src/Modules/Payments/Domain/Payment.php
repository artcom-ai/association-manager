<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Domain;

defined('ABSPATH') || exit;

final class Payment
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $memberId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $method,
        public readonly ?string $reference,
        public readonly ?string $paidAt,
    ) {
    }

    public static function draft(
        int $memberId,
        int $amountCents,
        string $currency,
        ?string $method,
        ?string $reference
    ): self {
        return new self(
            id: null,
            memberId: $memberId,
            amountCents: $amountCents,
            currency: $currency,
            status: PaymentStatus::PENDING,
            method: $method,
            reference: $reference,
            paidAt: null,
        );
    }

    public function complete(string $paidAt): self
    {
        return new self(
            id: $this->id,
            memberId: $this->memberId,
            amountCents: $this->amountCents,
            currency: $this->currency,
            status: PaymentStatus::COMPLETED,
            method: $this->method,
            reference: $this->reference,
            paidAt: $paidAt,
        );
    }

    public function fail(): self
    {
        return new self(
            id: $this->id,
            memberId: $this->memberId,
            amountCents: $this->amountCents,
            currency: $this->currency,
            status: PaymentStatus::FAILED,
            method: $this->method,
            reference: $this->reference,
            paidAt: $this->paidAt,
        );
    }

    public function refund(): self
    {
        if ($this->status !== PaymentStatus::COMPLETED) {
            throw new \LogicException('Only completed payments can be refunded.');
        }

        return new self(
            id: $this->id,
            memberId: $this->memberId,
            amountCents: $this->amountCents,
            currency: $this->currency,
            status: PaymentStatus::REFUNDED,
            method: $this->method,
            reference: $this->reference,
            paidAt: $this->paidAt,
        );
    }
}
