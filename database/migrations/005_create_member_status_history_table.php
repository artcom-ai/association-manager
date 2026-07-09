<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class() implements MigrationInterface {
    public function id(): string {
        return '005_create_member_status_history_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'member_status_history' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_id BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(50) NULL,
                to_status VARCHAR(50) NOT NULL,
                changed_by BIGINT UNSIGNED NULL,
                reason VARCHAR(255) NULL,
                changed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY member_id (member_id)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
