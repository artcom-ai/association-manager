<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Repositories;

use AssociationManager\Core\Fields\FieldDefinition;

defined( 'ABSPATH' ) || exit;

interface FieldDefinitionRepositoryInterface {

    /**
     * @return FieldDefinition[] ordered by display_order, then id
     */
    public function all( string $entityType ): array;

    /**
     * Every distinct entity_type that has at least one field defined -
     * lets the boot-time loader populate FieldRegistry without Core
     * needing to hardcode "member" or any other entity type.
     *
     * @return string[]
     */
    public function allEntityTypes(): array;

    public function find( string $entityType, string $key ): ?FieldDefinition;

    /**
     * Upsert keyed by (entity_type, field_key).
     */
    public function save( string $entityType, FieldDefinition $field ): void;

    public function delete( string $entityType, string $key ): void;
}
