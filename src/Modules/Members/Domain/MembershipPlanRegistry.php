<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined('ABSPATH') || exit;

/**
 * Extension point for membership plans (duration + grace period), same
 * idiom as MemberStatusRegistry and Core\Fields\FieldRegistry. Starts
 * empty - plan names/durations are entirely implementation-specific,
 * there is nothing universal to seed here.
 */
final class MembershipPlanRegistry
{
    /**
     * @var array<string, MembershipPlan>
     */
    private array $plans = [];

    public function register(MembershipPlan $plan): void
    {
        $this->plans[$plan->key] = $plan;
    }

    public function get(string $key): ?MembershipPlan
    {
        return $this->plans[$key] ?? null;
    }

    /**
     * @return MembershipPlan[]
     */
    public function all(): array
    {
        return array_values($this->plans);
    }
}
