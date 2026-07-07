<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Payments\Services;

use AssociationManager\Modules\Payments\Domain\PaymentStatus;
use AssociationManager\Modules\Payments\Repositories\PaymentRepository;
use AssociationManager\Modules\Payments\Services\PaymentService;
use AssociationManager\Tests\Support\TestCase;

final class PaymentServiceTest extends TestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService(new PaymentRepository());
    }

    public function testRecordCreatesAPendingPayment(): void
    {
        $payment = $this->service->record(1, 4999, 'EUR', 'card', 'ref-001');

        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertSame(4999, $payment->amountCents);
    }

    public function testCompleteThenRefund(): void
    {
        $payment = $this->service->record(1, 4999, 'EUR', 'card', 'ref-001');

        $completed = $this->service->complete($payment->id);
        $this->assertSame(PaymentStatus::COMPLETED, $completed->status);
        $this->assertNotNull($completed->paidAt);

        $refunded = $this->service->refund($payment->id);
        $this->assertSame(PaymentStatus::REFUNDED, $refunded->status);
    }

    public function testRefundingANonCompletedPaymentThrows(): void
    {
        $payment = $this->service->record(1, 1000, 'EUR', null, null);

        $this->expectException(\LogicException::class);

        $this->service->refund($payment->id);
    }

    public function testFailTransition(): void
    {
        $payment = $this->service->record(1, 1000, 'EUR', null, null);

        $failed = $this->service->fail($payment->id);

        $this->assertSame(PaymentStatus::FAILED, $failed->status);
    }

    public function testCompleteOnUnknownPaymentThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->complete(999999);
    }

    public function testForMemberFiltersByMemberId(): void
    {
        $this->service->record(1, 1000, 'EUR', null, null);
        $this->service->record(1, 2000, 'EUR', null, null);
        $this->service->record(2, 3000, 'EUR', null, null);

        $this->assertCount(2, $this->service->forMember(1));
        $this->assertCount(1, $this->service->forMember(2));
    }
}
