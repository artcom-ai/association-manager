<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Domain;

use AssociationManager\Modules\Members\Domain\MembershipPlan;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Tests\Support\TestCase;

final class MembershipPlanRegistryTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $registry = new MembershipPlanRegistry();

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->get('annual'));
    }

    public function testRegisterAndGet(): void
    {
        $registry = new MembershipPlanRegistry();
        $plan = new MembershipPlan('annual', 'Annual', durationDays: 365, gracePeriodDays: 30);

        $registry->register($plan);

        $this->assertSame($plan, $registry->get('annual'));
        $this->assertCount(1, $registry->all());
    }
}
