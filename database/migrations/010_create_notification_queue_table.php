<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class implements MigrationInterface {
    public function id(): string
    {
        return '010_create_notification_queue_table';
    }

    public function up(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = DatabaseManager::table('notification_queue');
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_key VARCHAR(100) NOT NULL,
                channel VARCHAR(20) NOT NULL DEFAULT 'email',
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                scheduled_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status_scheduled (status, scheduled_at)
            ) {$charset};
        ";

        dbDelta($sql);
    }
};
