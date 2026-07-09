<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Extension point for implementation-specific fields. Core never
 * registers anything here itself; a module (Members, Events, ...) or a
 * per-client implementation fetches this instance from the container
 * and calls register() for whatever fields it needs, for a given
 * entity type string (e.g. "member").
 */
final class FieldRegistry {

    /**
     * @var array<string, array<string, FieldDefinition>>
     */
    private array $fields = [];

    public function register( string $entityType, FieldDefinition $field ): void {
        $this->fields[ $entityType ][ $field->key ] = $field;
    }

    public function get( string $entityType, string $key ): ?FieldDefinition {
        return $this->fields[ $entityType ][ $key ] ?? null;
    }

    /**
     * @return FieldDefinition[]
     */
    public function forEntityType( string $entityType ): array {
        $fields = $this->fields[ $entityType ] ?? [];

        usort( $fields, static fn ( FieldDefinition $a, FieldDefinition $b ): int => $a->order <=> $b->order );

        return $fields;
    }
}
