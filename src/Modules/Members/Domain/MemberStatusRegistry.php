<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Extension point for statuses/transitions. Members registers the
 * built-in vocabulary here at construction time; a future implementation
 * module can fetch this same instance from the container and call
 * register()/allowTransition() to add its own statuses without Core or
 * Members ever needing to know about them.
 */
final class MemberStatusRegistry {

    /**
     * @var array<string, StatusDefinition>
     */
    private array $statuses = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $transitions = [];

    public function __construct() {
        $this->registerBuiltIns();
    }

    public function register( StatusDefinition $status ): void {
        $this->statuses[ $status->key ] = $status;
    }

    public function allowTransition( string $from, string $to ): void {
        $this->transitions[ $from ][ $to ] = true;
    }

    public function isTransitionAllowed( string $from, string $to ): bool {
        if ( $from === $to ) {
            return false;
        }

        return isset( $this->transitions[ $from ][ $to ] );
    }

    public function get( string $key ): ?StatusDefinition {
        return $this->statuses[ $key ] ?? null;
    }

    /**
     * @return StatusDefinition[]
     */
    public function all(): array {
        return array_values( $this->statuses );
    }

    private function registerBuiltIns(): void {
        $this->register( new StatusDefinition( MemberStatus::CANDIDATE, 'Candidate', isActive: false, isTerminal: false ) );
        $this->register( new StatusDefinition( MemberStatus::ACTIVE, 'Active', isActive: true, isTerminal: false ) );
        $this->register( new StatusDefinition( MemberStatus::INACTIVE, 'Inactive', isActive: false, isTerminal: true ) );
        $this->register( new StatusDefinition( MemberStatus::SUSPENDED, 'Suspended', isActive: false, isTerminal: false ) );
        $this->register( new StatusDefinition( MemberStatus::EXPIRED, 'Expired', isActive: false, isTerminal: false ) );
        $this->register( new StatusDefinition( MemberStatus::HONORARY, 'Honorary', isActive: true, isTerminal: false ) );

        $this->allowTransition( MemberStatus::CANDIDATE, MemberStatus::ACTIVE );
        $this->allowTransition( MemberStatus::CANDIDATE, MemberStatus::INACTIVE );
        $this->allowTransition( MemberStatus::ACTIVE, MemberStatus::SUSPENDED );
        $this->allowTransition( MemberStatus::ACTIVE, MemberStatus::EXPIRED );
        $this->allowTransition( MemberStatus::ACTIVE, MemberStatus::INACTIVE );
        $this->allowTransition( MemberStatus::ACTIVE, MemberStatus::HONORARY );
        $this->allowTransition( MemberStatus::SUSPENDED, MemberStatus::ACTIVE );
        $this->allowTransition( MemberStatus::SUSPENDED, MemberStatus::INACTIVE );
        $this->allowTransition( MemberStatus::EXPIRED, MemberStatus::ACTIVE );
        $this->allowTransition( MemberStatus::EXPIRED, MemberStatus::INACTIVE );
        $this->allowTransition( MemberStatus::HONORARY, MemberStatus::INACTIVE );
        $this->allowTransition( MemberStatus::INACTIVE, MemberStatus::ACTIVE );
    }
}
