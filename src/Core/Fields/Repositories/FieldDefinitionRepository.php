<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Repositories;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;

defined( 'ABSPATH' ) || exit;

final class FieldDefinitionRepository implements FieldDefinitionRepositoryInterface {

    /**
     * @return FieldDefinition[]
     */
    public function all( string $entityType ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_definitions' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_type = %s ORDER BY display_order ASC, id ASC",
                $entityType
            ),
            ARRAY_A
        );

        return array_map( fn ( array $row ): FieldDefinition => $this->hydrate( $row ), $rows ?: [] );
    }

    /**
     * @return string[]
     */
    public function allEntityTypes(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_definitions' );

        $rows = $wpdb->get_results( "SELECT DISTINCT entity_type FROM {$table}", ARRAY_A );

        // array_unique() rather than relying solely on SQL DISTINCT -
        // FakeWpdb (the test double) doesn't parse SELECT column lists,
        // so this keeps behavior identical against real MySQL and tests.
        return array_values( array_unique( array_map( static fn ( array $row ): string => $row['entity_type'], $rows ?: [] ) ) );
    }

    public function find( string $entityType, string $key ): ?FieldDefinition {
        global $wpdb;

        $table = DatabaseManager::table( 'field_definitions' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_type = %s AND field_key = %s",
                $entityType,
                $key
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    public function save( string $entityType, FieldDefinition $field ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'field_definitions' );
        $now   = current_time( 'mysql' );

        $data    = [
            'entity_type'                 => $entityType,
            'field_key'                   => $field->key,
            'label'                       => $field->label,
            'type'                        => $field->type,
            'required'                    => $field->required ? 1 : 0,
            'options'                     => $field->options !== null ? wp_json_encode( $field->options ) : null,
            'min_length'                  => $field->minLength,
            'max_length'                  => $field->maxLength,
            'min_value'                   => $field->minValue,
            'max_value'                   => $field->maxValue,
            'help_text'                   => $field->helpText,
            'display_order'               => $field->order,
            'visibility'                  => $field->visibility,
            'show_in_list'                => $field->showInList ? 1 : 0,
            'requires_approval_to_change' => $field->requiresApprovalToChange ? 1 : 0,
            'updated_at'                  => $now,
        ];
        $formats = [ '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%f', '%f', '%s', '%d', '%s', '%d', '%d', '%s' ];

        $existing = $this->find( $entityType, $field->key );

        if ( $existing === null ) {
            $data['created_at'] = $now;
            $formats[]          = '%s';

            $result = $wpdb->insert( $table, $data, $formats );

            if ( $result === false ) {
                throw new DatabaseWriteException(
                    "Failed to insert field definition '{$entityType}.{$field->key}': {$wpdb->last_error}"
                );
            }

            return;
        }

        $result = $wpdb->update(
            $table,
            $data,
            [
				'entity_type' => $entityType,
				'field_key'   => $field->key,
			],
            $formats,
            [ '%s', '%s' ]
        );

        if ( $result === false ) {
            throw new DatabaseWriteException(
                "Failed to update field definition '{$entityType}.{$field->key}': {$wpdb->last_error}"
            );
        }
    }

    public function delete( string $entityType, string $key ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'field_definitions' );

        $wpdb->delete(
            $table,
            [
				'entity_type' => $entityType,
				'field_key'   => $key,
			],
            [ '%s', '%s' ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): FieldDefinition {
        $options = null;

        if ( ! empty( $row['options'] ) ) {
            $decoded = json_decode( (string) $row['options'], true );
            $options = is_array( $decoded ) ? $decoded : null;
        }

        return new FieldDefinition(
            key: $row['field_key'],
            label: $row['label'],
            type: $row['type'],
            required: (bool) $row['required'],
            options: $options,
            minLength: $row['min_length'] !== null ? (int) $row['min_length'] : null,
            maxLength: $row['max_length'] !== null ? (int) $row['max_length'] : null,
            minValue: $row['min_value'] !== null ? (float) $row['min_value'] : null,
            maxValue: $row['max_value'] !== null ? (float) $row['max_value'] : null,
            helpText: $row['help_text'],
            order: (int) $row['display_order'],
            visibility: $row['visibility'],
            showInList: (bool) ( $row['show_in_list'] ?? false ),
            requiresApprovalToChange: (bool) ( $row['requires_approval_to_change'] ?? false ),
        );
    }
}
