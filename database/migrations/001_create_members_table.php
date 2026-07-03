<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function id(): string
    {
        return '001_create_members_table';
    }

    public function up(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = DatabaseManager::table('members');
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                wp_user_id BIGINT UNSIGNED NULL,
                member_number VARCHAR(50) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'candidate',
                membership_type VARCHAR(100) NULL,
                joined_at DATETIME NULL,
                approved_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY wp_user_id (wp_user_id),
                KEY member_number (member_number),
                KEY status (status)
            ) {$charset};
        ";

        dbDelta($sql);
    }
};