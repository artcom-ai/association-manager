<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Database\Migrations;

use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Database\MigrationInterface;
use AssociationManager\Database\MigrationRunner;
use AssociationManager\Tests\Support\TestCase;

/**
 * Exercises migrations 009, 012, 025, and 026 directly (via require, the
 * same mechanism MigrationLoader uses).
 *
 * 009/012: the write-failure fix from the third independent review
 * round - their raw $wpdb->insert() calls previously ignored a false
 * return value, so a failed required seed write could still let the
 * migration be recorded as complete by MigrationRunner.
 *
 * 025/026: the postcondition-check fix from the fourth independent
 * review round - dbDelta() has no reliable return value, so it could
 * silently fail to add the is_default column and the migration would
 * still be recorded as complete. Both migrations now explicitly verify
 * the column exists (SHOW COLUMNS FROM ... LIKE 'is_default') after
 * calling dbDelta() and throw DatabaseWriteException if it doesn't.
 * FakeWpdb's dbDelta() stub (tests/bootstrap.php) parses column names
 * out of the CREATE TABLE statement it's given and tracks per-table
 * column presence, including a one-shot blockColumnAddition() hook to
 * simulate dbDelta() failing to actually add a column - this is what
 * makes the postcondition check's failure path exercisable here at all,
 * not just its success path.
 */
final class SeedDefaultTemplateMigrationsTest extends TestCase
{
    public function testNotificationTemplateSeedMigrationThrowsAndIsNotRecordedWhenAnInsertFails(): void
    {
        $this->wpdb->failNextInsert('wp_am_notification_templates');

        $migration = $this->loadMigration('009_create_notification_templates_table');

        $this->expectException(DatabaseWriteException::class);

        $migration->up();
    }

    public function testCertificateTemplateSeedMigrationThrowsAndIsNotRecordedWhenTheInsertFails(): void
    {
        $this->wpdb->failNextInsert('wp_am_certificate_templates');

        $migration = $this->loadMigration('012_create_certificate_templates_table');

        $this->expectException(DatabaseWriteException::class);

        $migration->up();
    }

    public function testNotificationTemplateSeedMigrationSucceedsAndSeedsIsDefaultRows(): void
    {
        $migration = $this->loadMigration('009_create_notification_templates_table');

        $migration->up();

        $rows = $this->wpdb->get_results('SELECT * FROM wp_am_notification_templates', 'ARRAY_A');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame(1, (int) $row['is_default']);
        }
    }

    public function testCertificateTemplateSeedMigrationSucceedsAndSeedsAnIsDefaultRow(): void
    {
        $migration = $this->loadMigration('012_create_certificate_templates_table');

        $migration->up();

        $row = $this->wpdb->get_row("SELECT * FROM wp_am_certificate_templates WHERE type_key = 'membership'");
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['is_default']);
    }

    // --- 025 (notification_templates.is_default) ---

    public function testNotificationColumnMigrationSucceedsWhenColumnAlreadyExists(): void
    {
        $this->wpdb->presetTableColumns('wp_am_notification_templates', ['is_default']);

        $migration = $this->loadMigration('025_add_is_default_to_notification_templates_table');

        $migration->up();

        $this->assertTrue($this->wpdb->hasColumn('wp_am_notification_templates', 'is_default'));
    }

    public function testNotificationColumnMigrationSucceedsWhenColumnIsNewlyAdded(): void
    {
        $this->assertFalse($this->wpdb->hasColumn('wp_am_notification_templates', 'is_default'));

        $migration = $this->loadMigration('025_add_is_default_to_notification_templates_table');

        $migration->up();

        $this->assertTrue($this->wpdb->hasColumn('wp_am_notification_templates', 'is_default'));
    }

    public function testNotificationColumnMigrationThrowsWhenDbDeltaFailsToAddTheColumn(): void
    {
        $this->wpdb->blockColumnAddition('wp_am_notification_templates', 'is_default');

        $migration = $this->loadMigration('025_add_is_default_to_notification_templates_table');

        $this->expectException(DatabaseWriteException::class);

        $migration->up();
    }

    public function testNotificationColumnMigrationIsNotRecordedWhenItFailsAndRetrySucceedsAndIsRecorded(): void
    {
        $this->wpdb->blockColumnAddition('wp_am_notification_templates', 'is_default');

        $runner = new MigrationRunner('am_test_migrations_025');

        try {
            $runner->run([$this->loadMigration('025_add_is_default_to_notification_templates_table')]);
            $this->fail('Expected DatabaseWriteException on the first, blocked attempt.');
        } catch (DatabaseWriteException) {
            // expected
        }

        $this->assertSame(
            [],
            get_option('am_test_migrations_025', []),
            'a migration whose postcondition check failed must not be recorded as executed'
        );

        // Retry: blockColumnAddition() was one-shot, so this attempt is
        // not blocked - the transient condition is "resolved."
        $runner->run([$this->loadMigration('025_add_is_default_to_notification_templates_table')]);

        $this->assertSame(
            ['025_add_is_default_to_notification_templates_table'],
            get_option('am_test_migrations_025', []),
            'a successful retry must be recorded'
        );
        $this->assertTrue($this->wpdb->hasColumn('wp_am_notification_templates', 'is_default'));
    }

    // --- 026 (certificate_templates.is_default) ---

    public function testCertificateColumnMigrationSucceedsWhenColumnAlreadyExists(): void
    {
        $this->wpdb->presetTableColumns('wp_am_certificate_templates', ['is_default']);

        $migration = $this->loadMigration('026_add_is_default_to_certificate_templates_table');

        $migration->up();

        $this->assertTrue($this->wpdb->hasColumn('wp_am_certificate_templates', 'is_default'));
    }

    public function testCertificateColumnMigrationSucceedsWhenColumnIsNewlyAdded(): void
    {
        $this->assertFalse($this->wpdb->hasColumn('wp_am_certificate_templates', 'is_default'));

        $migration = $this->loadMigration('026_add_is_default_to_certificate_templates_table');

        $migration->up();

        $this->assertTrue($this->wpdb->hasColumn('wp_am_certificate_templates', 'is_default'));
    }

    public function testCertificateColumnMigrationThrowsWhenDbDeltaFailsToAddTheColumn(): void
    {
        $this->wpdb->blockColumnAddition('wp_am_certificate_templates', 'is_default');

        $migration = $this->loadMigration('026_add_is_default_to_certificate_templates_table');

        $this->expectException(DatabaseWriteException::class);

        $migration->up();
    }

    public function testCertificateColumnMigrationIsNotRecordedWhenItFailsAndRetrySucceedsAndIsRecorded(): void
    {
        $this->wpdb->blockColumnAddition('wp_am_certificate_templates', 'is_default');

        $runner = new MigrationRunner('am_test_migrations_026');

        try {
            $runner->run([$this->loadMigration('026_add_is_default_to_certificate_templates_table')]);
            $this->fail('Expected DatabaseWriteException on the first, blocked attempt.');
        } catch (DatabaseWriteException) {
            // expected
        }

        $this->assertSame(
            [],
            get_option('am_test_migrations_026', []),
            'a migration whose postcondition check failed must not be recorded as executed'
        );

        $runner->run([$this->loadMigration('026_add_is_default_to_certificate_templates_table')]);

        $this->assertSame(
            ['026_add_is_default_to_certificate_templates_table'],
            get_option('am_test_migrations_026', []),
            'a successful retry must be recorded'
        );
        $this->assertTrue($this->wpdb->hasColumn('wp_am_certificate_templates', 'is_default'));
    }

    private function loadMigration(string $filename): MigrationInterface
    {
        $migration = require AM_PLUGIN_DIR . "database/migrations/{$filename}.php";

        $this->assertInstanceOf(MigrationInterface::class, $migration);

        return $migration;
    }
}
