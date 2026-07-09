<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Purely additive - same full-desired-schema dbDelta() pattern as
// migration 004/008/015. Three new nullable columns so any importer
// (MemberPress today, others later) can record where a member's data
// came from without altering any existing column's nullability.
return new class() implements MigrationInterface {
    public function id(): string {
        return '016_add_import_source_to_members_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'members' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                wp_user_id BIGINT UNSIGNED NULL,
                uuid CHAR(36) NULL,
                member_number VARCHAR(50) NULL,
                email VARCHAR(255) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'candidate',
                membership_type VARCHAR(100) NULL,
                joined_at DATETIME NULL,
                expires_at DATETIME NULL,
                approved_at DATETIME NULL,
                source_system VARCHAR(50) NULL,
                source_user_id BIGINT UNSIGNED NULL,
                imported_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uuid (uuid),
                KEY wp_user_id (wp_user_id),
                KEY member_number (member_number),
                KEY status (status),
                KEY source (source_system, source_user_id)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
