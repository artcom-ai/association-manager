<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class() implements MigrationInterface {
    public function id(): string {
        return '002_create_payments_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'payments' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_id BIGINT UNSIGNED NOT NULL,
                amount_cents BIGINT UNSIGNED NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                method VARCHAR(50) NULL,
                reference VARCHAR(100) NULL,
                paid_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY member_id (member_id),
                KEY status (status)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
