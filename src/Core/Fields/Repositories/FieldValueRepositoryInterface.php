<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Repositories;

defined( 'ABSPATH' ) || exit;

interface FieldValueRepositoryInterface {

    public function get( string $entityType, int $entityId, string $fieldKey ): ?string;

    /**
     * @return array<string, string>
     */
    public function allFor( string $entityType, int $entityId ): array;

    public function set( string $entityType, int $entityId, string $fieldKey, ?string $value ): void;

    /**
     * Removes every stored value for this field, across every entity -
     * used when an admin deletes the field definition itself, so
     * orphaned values don't linger silently under a key nothing
     * references anymore.
     */
    public function deleteForField( string $entityType, string $fieldKey ): void;

    /**
     * Stores $value as a pending replacement, leaving the current value
     * (returned by get()/allFor()) untouched - see ADR-023 addendum
     * (approval-gated field changes). At most one pending value per
     * field per entity; a second call before the first is resolved
     * overwrites the earlier pending value.
     */
    public function setPending( string $entityType, int $entityId, string $fieldKey, string $value ): void;

    /**
     * Promotes the pending value to the live value and clears the
     * pending slot. No-op if there is no pending value.
     */
    public function approvePending( string $entityType, int $entityId, string $fieldKey ): void;

    /**
     * Discards the pending value, leaving the current live value
     * untouched. No-op if there is no pending value.
     */
    public function rejectPending( string $entityType, int $entityId, string $fieldKey ): void;

    /**
     * @return array<string, string>
     */
    public function pendingFor( string $entityType, int $entityId ): array;
}
