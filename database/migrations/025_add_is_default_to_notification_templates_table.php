<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Database\MigrationInterface;

// Additive follow-up to migration 009, which was edited in place to add
// the is_default column - safe for a genuinely fresh install (009 runs
// for the first time with the new column already in its own CREATE
// TABLE), but not for a database where 009 was already recorded as
// executed under the ORIGINAL schema: MigrationRunner never re-runs a
// completed migration id, so that database's notification_templates
// table would otherwise be permanently missing is_default while
// NotificationTemplateRepository unconditionally reads/writes it.
//
// Same full-desired-schema dbDelta() pattern as every other purely
// additive column migration in this codebase (e.g. 020, 021, 022, 023),
// deliberately idempotent for both cases this needs to cover:
// - Database already has is_default (ran 009 after this fix shipped):
//   dbDelta() detects no schema difference and does nothing - existing
//   is_default = 1 rows are untouched.
// - Database is missing is_default (ran the original 009 before this
//   fix shipped): dbDelta() issues ALTER TABLE ... ADD COLUMN
//   is_default TINYINT(1) NOT NULL DEFAULT 0. MySQL backfills every
//   existing row - English defaults nobody ever touched, or an
//   administrator's edited Greek content - to is_default = 0. This is
//   deliberately conservative: this migration has no way to tell which
//   of those two states any given pre-existing row is actually in, so
//   it treats every one of them as "possibly administrator-edited,
//   never overwrite" rather than guessing from content.
//
// See docs/adr/024-elesyth-implementation-migrations.md's third
// addendum for the full incident this migration exists to fix.
return new class() implements MigrationInterface {
    public function id(): string {
        return '025_add_is_default_to_notification_templates_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'notification_templates' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_key VARCHAR(100) NOT NULL,
                channel VARCHAR(20) NOT NULL DEFAULT 'email',
                subject VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY event_channel (event_key, channel)
            ) {$charset};
        ";

        dbDelta( $sql );

        // dbDelta() has no reliable return value to check - it can
        // silently fail to add a column (insufficient DB privileges, a
        // locked table, a dbDelta parsing quirk on some MySQL/MariaDB
        // version) and this migration would otherwise still be recorded
        // as complete by MigrationRunner, permanently hiding the gap
        // this whole migration exists to close. Verify the postcondition
        // explicitly instead of trusting dbDelta() ran successfully.
        $column = $wpdb->get_row(
            $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'is_default' )
        );

        if ( $column === null ) {
            throw new DatabaseWriteException(
                "Migration 025 failed: dbDelta() did not add the is_default column to '{$table}'."
            );
        }
    }
};
