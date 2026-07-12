<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Database\MigrationInterface;

// Additive follow-up to migration 012 - same reasoning as
// 025_add_is_default_to_notification_templates_table.php, applied to
// certificate_templates instead. See that migration's comment for the
// full explanation, including why the postcondition check below exists.
return new class() implements MigrationInterface {
    public function id(): string {
        return '026_add_is_default_to_certificate_templates_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'certificate_templates' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type_key VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                html_body LONGTEXT NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY type_key (type_key)
            ) {$charset};
        ";

        dbDelta( $sql );

        $column = $wpdb->get_row(
            $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'is_default' )
        );

        if ( $column === null ) {
            throw new DatabaseWriteException(
                "Migration 026 failed: dbDelta() did not add the is_default column to '{$table}'."
            );
        }
    }
};
