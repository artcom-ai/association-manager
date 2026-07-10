<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Field *definitions* (what fields exist, their type/label/visibility)
// were previously only ever registered in PHP code against
// Core\Fields\FieldRegistry - this table makes them admin-manageable at
// runtime instead. Field *values* (wp_am_field_values, migration 006)
// are unaffected - this only adds a persistent, editable source for the
// definitions themselves, loaded into the same FieldRegistry at boot
// (see ADR-023) so every existing consumer needs no changes.
return new class() implements MigrationInterface {
    public function id(): string {
        return '018_create_field_definitions_table';
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
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY entity_field (entity_type, field_key)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
