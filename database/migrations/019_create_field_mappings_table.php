<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Field *mappings* (Modules\Importers\FieldMappingRegistry) were
// previously only ever registered in PHP code by an implementation - this
// table makes them admin-manageable/persistent too, the same treatment
// migration 018 gave field *definitions*. Needed so an admin action that
// discovers and creates mappings from a real import source (see
// FieldDiscoveryService) actually survives past the current request.
return new class() implements MigrationInterface {
    public function id(): string {
        return '019_create_field_mappings_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'field_mappings' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_system VARCHAR(50) NOT NULL,
                source_key VARCHAR(190) NOT NULL,
                target_field_key VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY source_mapping (source_system, source_key)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
