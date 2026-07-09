<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Purely additive - same full-desired-schema dbDelta() pattern as
// migration 004/008. Adds a status column (draft/issued/revoked);
// existing columns are untouched, so there's no NOT-NULL/nullability
// change for dbDelta to attempt (a known dbDelta limitation this
// project avoids by design - see ADR-019's addendum).
return new class() implements MigrationInterface {
    public function id(): string {
        return '015_add_status_to_certificates_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'certificates' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_id BIGINT UNSIGNED NOT NULL,
                type_key VARCHAR(100) NOT NULL,
                wp_attachment_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                issued_at DATETIME NOT NULL,
                issued_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY member_id (member_id),
                KEY status (status)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
