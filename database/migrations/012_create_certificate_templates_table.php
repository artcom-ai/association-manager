<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Database\MigrationInterface;

return new class() implements MigrationInterface {
    public function id(): string {
        return '012_create_certificate_templates_table';
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

        $this->seedDefault( $table );
    }

    /**
     * Ship with one real, editable certificate rather than an empty
     * table - same reasoning as migration 009's notification defaults,
     * including the is_default provenance marker and the additive
     * follow-up migration for pre-existing databases (see that
     * migration's docblock for the full explanation).
     */
    private function seedDefault( string $table ): void {
        global $wpdb;

        $typeKey = 'membership';

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE type_key = %s", $typeKey )
        );

        if ( $exists ) {
            return;
        }

        $html = <<<'HTML'
            <html>
            <body style="text-align: center; font-family: sans-serif; padding: 80px;">
                <h1>Certificate of Membership</h1>
                <p style="font-size: 18px;">This certifies that</p>
                <h2>{member_number}</h2>
                <p style="font-size: 18px;">is a {membership_type} member in good standing.</p>
                <p>Issued on {issued_at}</p>
            </body>
            </html>
            HTML;

        $result = $wpdb->insert(
            $table,
            [
                'type_key'   => $typeKey,
                'name'       => 'Membership Certificate',
                'html_body'  => $html,
                'is_default' => 1,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s' ]
        );

        if ( $result === false ) {
            throw new DatabaseWriteException(
                "Failed to seed default certificate template '{$typeKey}': {$wpdb->last_error}"
            );
        }
    }
};
