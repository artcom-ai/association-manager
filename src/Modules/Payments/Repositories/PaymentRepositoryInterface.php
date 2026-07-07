<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Repositories;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
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
     * @return PaginatedResult<Payment>
     */
    public function paginate(PaginationParams $params): PaginatedResult;

    /**
     * @return Payment[]
     */
    public function allForMember(int $memberId): array;

    public function insert(Payment $payment): int;

    public function update(Payment $payment): void;
}
