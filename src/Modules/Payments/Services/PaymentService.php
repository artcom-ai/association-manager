<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Services;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Payments\Domain\Payment;
use AssociationManager\Modules\Payments\Repositories\PaymentRepositoryInterface;

defined('ABSPATH') || exit;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository
    ) {
    }

    public function record(
        int $memberId,
        int $amountCents,
        string $currency,
        ?string $method,
        ?string $reference
    ): Payment {
        $id = $this->repository->insert(
            Payment::draft($memberId, $amountCents, $currency, $method, $reference)
        );

        return $this->mustFind($id);
    }

    public function complete(int $paymentId): Payment
    {
        $completed = $this->mustFind($paymentId)->complete(current_time('mysql'));

        $this->repository->update($completed);

        return $completed;
    }

    public function fail(int $paymentId): Payment
    {
        $failed = $this->mustFind($paymentId)->fail();

        $this->repository->update($failed);

        return $failed;
    }

    public function refund(int $paymentId): Payment
    {
        $refunded = $this->mustFind($paymentId)->refund();

        $this->repository->update($refunded);

        return $refunded;
    }

    public function find(int $id): ?Payment
    {
        return $this->repository->find($id);
    }

    /**
     * @return Payment[]
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return PaginatedResult<Payment>
     */
    public function paginate(PaginationParams $params): PaginatedResult
    {
        return $this->repository->paginate($params);
    }

    /**
     * @return Payment[]
     */
    public function forMember(int $memberId): array
    {
        return $this->repository->allForMember($memberId);
    }

    private function mustFind(int $id): Payment
    {
        $payment = $this->repository->find($id);

        if ($payment === null) {
            throw new \RuntimeException("Payment not found: {$id}");
        }

        return $payment;
    }
}
