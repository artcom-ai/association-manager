<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Repositories;

use AssociationManager\Modules\Payments\Domain\Payment;

defined('ABSPATH') || exit;

interface PaymentRepositoryInterface
{
    public function find(int $id): ?Payment;

    /**
     * @return Payment[]
     */
    public function all(): array;

    /**
     * @return Payment[]
     */
    public function allForMember(int $memberId): array;

    public function insert(Payment $payment): int;

    public function update(Payment $payment): void;
}
