<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Modules\Importers\Domain\FieldMapping;

defined( 'ABSPATH' ) || exit;

final class FieldMappingRepository implements FieldMappingRepositoryInterface {

    /**
     * @return FieldMapping[]
     */
    public function all( string $sourceSystem ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_mappings' );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_system = %s ORDER BY id ASC",
                $sourceSystem
            ),
            ARRAY_A
        );

        return array_map( fn ( array $row ): FieldMapping => $this->hydrate( $row ), $rows ?: [] );
    }

    public function find( string $sourceSystem, string $sourceKey ): ?FieldMapping {
        global $wpdb;

        $table = DatabaseManager::table( 'field_mappings' );

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_system = %s AND source_key = %s",
                $sourceSystem,
                $sourceKey
            ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    public function save( string $sourceSystem, FieldMapping $mapping ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'field_mappings' );
        $now   = current_time( 'mysql' );

        $data    = [
            'source_system'    => $sourceSystem,
            'source_key'       => $mapping->sourceKey,
            'target_field_key' => $mapping->targetFieldKey,
            'updated_at'       => $now,
        ];
        $formats = [ '%s', '%s', '%s', '%s' ];

        $existing = $this->find( $sourceSystem, $mapping->sourceKey );

        if ( $existing === null ) {
            $data['created_at'] = $now;
            $formats[]          = '%s';

            $result = $wpdb->insert( $table, $data, $formats );

            if ( $result === false ) {
                throw new DatabaseWriteException(
                    "Failed to insert field mapping '{$sourceSystem}.{$mapping->sourceKey}': {$wpdb->last_error}"
                );
            }

            return;
        }

        $result = $wpdb->update(
            $table,
            $data,
            [
				'source_system' => $sourceSystem,
				'source_key'    => $mapping->sourceKey,
			],
            $formats,
            [ '%s', '%s' ]
        );

        if ( $result === false ) {
            throw new DatabaseWriteException(
                "Failed to update field mapping '{$sourceSystem}.{$mapping->sourceKey}': {$wpdb->last_error}"
            );
        }
    }

    /**
     * @return string[]
     */
    public function allSourceSystems(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'field_mappings' );

        $rows = $wpdb->get_results( "SELECT DISTINCT source_system FROM {$table}", ARRAY_A );

        // array_unique() rather than relying solely on SQL DISTINCT -
        // FakeWpdb (the test double) doesn't parse SELECT column lists,
        // so this keeps behavior identical against real MySQL and tests.
        return array_values( array_unique( array_map( static fn ( array $row ): string => $row['source_system'], $rows ?: [] ) ) );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): FieldMapping {
        return new FieldMapping( $row['source_key'], $row['target_field_key'] );
    }
}
