<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Repositories;

use AssociationManager\Database\DatabaseManager;

defined( 'ABSPATH' ) || exit;

final class FieldValueRepository implements FieldValueRepositoryInterface {

    public function get( string $entityType, int $entityId, string $fieldKey ): ?string {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT value FROM {$table} WHERE entity_type = %s AND entity_id = %d AND field_key = %s",
                $entityType,
                $entityId,
                $fieldKey
            )
        );

        return $value === null ? null : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    public function allFor( string $entityType, int $entityId ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_key, value FROM {$table} WHERE entity_type = %s AND entity_id = %d",
                $entityType,
                $entityId
            ),
            ARRAY_A
        );

        $values = [];

        foreach ( $rows ?: [] as $row ) {
            $values[ $row['field_key'] ] = $row['value'];
        }

        return $values;
    }

    public function set( string $entityType, int $entityId, string $fieldKey, ?string $value ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );

        if ( $value === null ) {
            $wpdb->delete(
                $table,
                [
					'entity_type' => $entityType,
					'entity_id'   => $entityId,
					'field_key'   => $fieldKey,
				],
                [ '%s', '%d', '%s' ]
            );

            return;
        }

        $now = current_time( 'mysql' );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (entity_type, entity_id, field_key, value, created_at, updated_at)
             VALUES (%s, %d, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)",
                $entityType,
                $entityId,
                $fieldKey,
                $value,
                $now,
                $now
            )
        );
    }

    public function deleteForField( string $entityType, string $fieldKey ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );

        $wpdb->delete(
            $table,
            [
				'entity_type' => $entityType,
				'field_key'   => $fieldKey,
			],
            [ '%s', '%s' ]
        );
    }

    /**
     * Upsert-by-key, hand-written find-then-insert-or-update - same
     * pattern (and same reasoning: a raw prepared INSERT can't express
     * SQL NULL for a nullable column via %s/%d) established in ADR-023
     * for FieldDefinitionRepository/FieldMappingRepository. A pending
     * write must never touch the current live `value` column, only the
     * two pending_* columns - the update branch's $data deliberately
     * omits `value`.
     */
    public function setPending( string $entityType, int $entityId, string $fieldKey, string $value ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );
        $now   = current_time( 'mysql' );

        if ( $this->findRow( $entityType, $entityId, $fieldKey ) === null ) {
            $wpdb->insert(
                $table,
                [
					'entity_type'          => $entityType,
					'entity_id'            => $entityId,
					'field_key'            => $fieldKey,
					'value'                => null,
					'pending_value'        => $value,
					'pending_submitted_at' => $now,
					'created_at'           => $now,
					'updated_at'           => $now,
				],
                [ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );

            return;
        }

        $wpdb->update(
            $table,
            [
				'pending_value'        => $value,
				'pending_submitted_at' => $now,
				'updated_at'           => $now,
			],
            [
				'entity_type' => $entityType,
				'entity_id'   => $entityId,
				'field_key'   => $fieldKey,
			],
            [ '%s', '%s', '%s' ],
            [ '%s', '%d', '%s' ]
        );
    }

    public function approvePending( string $entityType, int $entityId, string $fieldKey ): void {
        global $wpdb;

        $existing = $this->findRow( $entityType, $entityId, $fieldKey );

        if ( $existing === null || ( $existing['pending_value'] ?? null ) === null ) {
            return;
        }

        $wpdb->update(
            DatabaseManager::table( 'field_values' ),
            [
				'value'                => $existing['pending_value'],
				'pending_value'        => null,
				'pending_submitted_at' => null,
				'updated_at'           => current_time( 'mysql' ),
			],
            [
				'entity_type' => $entityType,
				'entity_id'   => $entityId,
				'field_key'   => $fieldKey,
			],
            [ '%s', '%s', '%s', '%s' ],
            [ '%s', '%d', '%s' ]
        );
    }

    public function rejectPending( string $entityType, int $entityId, string $fieldKey ): void {
        global $wpdb;

        $wpdb->update(
            DatabaseManager::table( 'field_values' ),
            [
				'pending_value'        => null,
				'pending_submitted_at' => null,
				'updated_at'           => current_time( 'mysql' ),
			],
            [
				'entity_type' => $entityType,
				'entity_id'   => $entityId,
				'field_key'   => $fieldKey,
			],
            [ '%s', '%s', '%s' ],
            [ '%s', '%d', '%s' ]
        );
    }

    /**
     * @return array<string, string>
     */
    public function pendingFor( string $entityType, int $entityId ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT field_key, pending_value FROM {$table} WHERE entity_type = %s AND entity_id = %d",
                $entityType,
                $entityId
            ),
            ARRAY_A
        );

        $values = [];

        foreach ( $rows ?: [] as $row ) {
            $pendingValue = $row['pending_value'] ?? null;

            if ( $pendingValue === null ) {
                continue;
            }

            $values[ $row['field_key'] ] = $pendingValue;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow( string $entityType, int $entityId, string $fieldKey ): ?array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_values' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %d AND field_key = %s",
                $entityType,
                $entityId,
                $fieldKey
            ),
            ARRAY_A
        );

        return $row ?: null;
    }
}
