<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Repositories;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Payments\Domain\Payment;

defined('ABSPATH') || exit;

final class PaymentRepository implements PaymentRepositoryInterface
{
    public function find(int $id): ?Payment
    {
        global $wpdb;

        $table = DatabaseManager::table('payments');

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return Payment[]
     */
    public function all(): array
    {
        global $wpdb;

        $table = DatabaseManager::table('payments');

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);

        return array_map(fn (array $row): Payment => $this->hydrate($row), $rows ?: []);
    }

    public function paginate(PaginationParams $params): PaginatedResult
    {
        global $wpdb;

        $table = DatabaseManager::table('payments');

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
                $params->limit(),
                $params->offset()
            ),
            ARRAY_A
        );

        $payments = array_map(fn (array $row): Payment => $this->hydrate($row), $rows ?: []);

        return new PaginatedResult($payments, $total, $params->page, $params->perPage);
    }

    /**
     * @return Payment[]
     */
    public function allForMember(int $memberId): array
    {
        global $wpdb;

        $table = DatabaseManager::table('payments');

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE member_id = %d ORDER BY id DESC", $memberId),
            ARRAY_A
        );

        return array_map(fn (array $row): Payment => $this->hydrate($row), $rows ?: []);
    }

    public function insert(Payment $payment): int
    {
        global $wpdb;

        $table = DatabaseManager::table('payments');
        $now = current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'member_id' => $payment->memberId,
                'amount_cents' => $payment->amountCents,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paidAt,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function update(Payment $payment): void
    {
        if ($payment->id === null) {
            throw new \InvalidArgumentException('Cannot update a payment without an id.');
        }

        global $wpdb;

        $table = DatabaseManager::table('payments');

        $wpdb->update(
            $table,
            [
                'status' => $payment->status,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paidAt,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $payment->id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    private function hydrate(array $row): Payment
    {
        return new Payment(
            id: (int) $row['id'],
            memberId: (int) $row['member_id'],
            amountCents: (int) $row['amount_cents'],
            currency: $row['currency'],
            status: $row['status'],
            method: $row['method'],
            reference: $row['reference'],
            paidAt: $row['paid_at'],
        );
    }
}
