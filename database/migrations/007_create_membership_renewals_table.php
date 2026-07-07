<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function id(): string
    {
        return '007_create_membership_renewals_table';
    }

    public function up(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = DatabaseManager::table('membership_renewals');
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                member_id BIGINT UNSIGNED NOT NULL,
                plan_key VARCHAR(100) NULL,
                previous_expires_at DATETIME NULL,
                new_expires_at DATETIME NOT NULL,
                renewed_by BIGINT UNSIGNED NULL,
                renewed_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY member_id (member_id)
            ) {$charset};
        ";

        dbDelta($sql);
    }
};
