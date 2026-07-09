<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class() implements MigrationInterface {
    public function id(): string {
        return '013_create_certificates_table';
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
                issued_at DATETIME NOT NULL,
                issued_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY member_id (member_id)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
