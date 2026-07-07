<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Domain;

use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\StatusDefinition;
use AssociationManager\Tests\Support\TestCase;

final class MemberStatusRegistryTest extends TestCase
{
    private MemberStatusRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new MemberStatusRegistry();
    }

    public function testBuiltInStatusesAreSeeded(): void
    {
        $keys = array_map(static fn (StatusDefinition $status): string => $status->key, $this->registry->all());

        $this->assertEqualsCanonicalizing(
            [
                MemberStatus::CANDIDATE,
                MemberStatus::ACTIVE,
                MemberStatus::INACTIVE,
                MemberStatus::SUSPENDED,
                MemberStatus::EXPIRED,
                MemberStatus::HONORARY,
            ],
            $keys
        );
    }

    /**
     * @dataProvider allowedTransitionsProvider
     */
    public function testAllowedTransitions(string $from, string $to): void
    {
        $this->assertTrue($this->registry->isTransitionAllowed($from, $to), "{$from} -> {$to} should be allowed");
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function allowedTransitionsProvider(): iterable
    {
        yield 'candidate to active' => [MemberStatus::CANDIDATE, MemberStatus::ACTIVE];
        yield 'candidate to inactive' => [MemberStatus::CANDIDATE, MemberStatus::INACTIVE];
        yield 'active to suspended' => [MemberStatus::ACTIVE, MemberStatus::SUSPENDED];
        yield 'active to expired' => [MemberStatus::ACTIVE, MemberStatus::EXPIRED];
        yield 'active to inactive' => [MemberStatus::ACTIVE, MemberStatus::INACTIVE];
        yield 'active to honorary' => [MemberStatus::ACTIVE, MemberStatus::HONORARY];
        yield 'suspended to active' => [MemberStatus::SUSPENDED, MemberStatus::ACTIVE];
        yield 'suspended to inactive' => [MemberStatus::SUSPENDED, MemberStatus::INACTIVE];
        yield 'expired to active' => [MemberStatus::EXPIRED, MemberStatus::ACTIVE];
        yield 'expired to inactive' => [MemberStatus::EXPIRED, MemberStatus::INACTIVE];
        yield 'honorary to inactive' => [MemberStatus::HONORARY, MemberStatus::INACTIVE];
        yield 'inactive to active' => [MemberStatus::INACTIVE, MemberStatus::ACTIVE];
    }

    /**
     * @dataProvider rejectedTransitionsProvider
     */
    public function testRejectedTransitions(string $from, string $to): void
    {
        $this->assertFalse($this->registry->isTransitionAllowed($from, $to), "{$from} -> {$to} should NOT be allowed");
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function rejectedTransitionsProvider(): iterable
    {
        yield 'candidate to suspended' => [MemberStatus::CANDIDATE, MemberStatus::SUSPENDED];
        yield 'candidate to expired' => [MemberStatus::CANDIDATE, MemberStatus::EXPIRED];
        yield 'candidate to honorary' => [MemberStatus::CANDIDATE, MemberStatus::HONORARY];
        yield 'inactive to suspended' => [MemberStatus::INACTIVE, MemberStatus::SUSPENDED];
        yield 'honorary to suspended' => [MemberStatus::HONORARY, MemberStatus::SUSPENDED];
        yield 'same status is never a transition' => [MemberStatus::ACTIVE, MemberStatus::ACTIVE];
    }

    public function testCustomStatusCanBeRegisteredByAnImplementation(): void
    {
        $this->registry->register(new StatusDefinition('on_leave', 'On Leave', isActive: false, isTerminal: false));
        $this->registry->allowTransition(MemberStatus::ACTIVE, 'on_leave');

        $this->assertNotNull($this->registry->get('on_leave'));
        $this->assertTrue($this->registry->isTransitionAllowed(MemberStatus::ACTIVE, 'on_leave'));
    }
}
