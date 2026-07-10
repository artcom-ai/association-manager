<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Purely additive - same full-desired-schema dbDelta() pattern as
// migration 020. Lets an admin flag a custom field to also appear as a
// Members-list column (see ADR-023 addendum) - deliberately generic
// (any field, any install) rather than hardcoding a fixed extra-column
// set into MembersListTable, which would put implementation-specific
// knowledge into Core.
return new class() implements MigrationInterface {
    public function id(): string {
        return '021_add_show_in_list_to_field_definitions_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'field_definitions' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                label VARCHAR(190) NOT NULL,
                type VARCHAR(20) NOT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                options TEXT NULL,
                min_length INT NULL,
                max_length INT NULL,
                min_value DOUBLE NULL,
                max_value DOUBLE NULL,
                help_text TEXT NULL,
                display_order INT NOT NULL DEFAULT 0,
                visibility VARCHAR(20) NOT NULL DEFAULT 'admin',
                show_in_list TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY entity_field (entity_type, field_key)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
