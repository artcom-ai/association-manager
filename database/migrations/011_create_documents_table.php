<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

return new class() implements MigrationInterface {
    public function id(): string {
        return '011_create_documents_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'documents' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                category VARCHAR(100) NULL,
                wp_attachment_id BIGINT UNSIGNED NOT NULL,
                visibility VARCHAR(50) NOT NULL DEFAULT 'admin',
                uploaded_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY visibility (visibility),
                KEY category (category)
            ) {$charset};
        ";

        dbDelta( $sql );
    }
};
