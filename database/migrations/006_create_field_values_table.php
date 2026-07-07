<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function id(): string
    {
        return '006_create_field_values_table';
    }

    public function up(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = DatabaseManager::table('field_values');
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                value LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY entity_field (entity_type, entity_id, field_key),
                KEY entity (entity_type, entity_id)
            ) {$charset};
        ";

        dbDelta($sql);
    }
};
