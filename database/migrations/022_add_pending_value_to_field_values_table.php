<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Purely additive - same full-desired-schema dbDelta() pattern as every
// prior migration. A pending replacement value lives on the same row as
// the current value (same entity_type/entity_id/field_key uniqueness),
// not a separate table - there's at most one pending change per field
// per entity at a time, which this shape enforces for free. See ADR-023
// addendum (file access control / approval-gated changes).
return new class() implements MigrationInterface {
    public function id(): string {
        return '022_add_pending_value_to_field_values_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'field_values' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                value LONGTEXT NULL,
                pending_value LONGTEXT NULL,
                pending_submitted_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY entity_field (entity_type, entity_id, field_key),
                KEY entity (entity_type, entity_id)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
