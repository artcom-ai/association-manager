<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Repositories;

use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepository;
use AssociationManager\Tests\Support\TestCase;

final class NotificationTemplateRepositoryTest extends TestCase
{
    private NotificationTemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new NotificationTemplateRepository();
    }

    public function testSaveInsertsWhenNotExisting(): void
    {
        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject', 'Body'));

        $found = $this->repository->find('admin_new_member', 'email');
        $this->assertNotNull($found);
        $this->assertSame('Subject', $found->subject);
    }

    public function testSaveUpdatesWhenAlreadyExisting(): void
    {
        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject', 'Body'));
        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject v2', 'Body v2'));

        $all = $this->repository->all();
        $this->assertCount(1, $all);
        $this->assertSame('Subject v2', $all[0]->subject);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->find('no-such-event', 'email'));
    }

    public function testSaveThrowsWhenInsertFails(): void
    {
        $this->wpdb->failNextInsert('wp_am_notification_templates');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject', 'Body'));
    }

    public function testSaveThrowsWhenUpdateFails(): void
    {
        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject', 'Body'));

        $this->wpdb->failNextUpdate('wp_am_notification_templates');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject v2', 'Body v2'));
    }

    /**
     * Simulates the exact scenario the review flagged: seeding several
     * templates in a loop (as NotificationTemplateSeeder does), where a
     * later one fails after earlier ones already succeeded. The already
     * -saved rows must remain (no rollback expected - the repository has
     * no transaction), but the failure must still propagate so the
     * caller (ultimately MigrationRunner) knows this run was incomplete.
     */
    public function testFailureAfterPartialSeedingStillPropagates(): void
    {
        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject 1', 'Body 1'));
        $this->repository->save(new NotificationTemplate(null, 'member_activated', 'email', 'Subject 2', 'Body 2'));

        $this->wpdb->failNextInsert('wp_am_notification_templates');

        try {
            $this->repository->save(new NotificationTemplate(null, 'member_suspended', 'email', 'Subject 3', 'Body 3'));
            $this->fail('Expected DatabaseWriteException was not thrown.');
        } catch (DatabaseWriteException) {
            // expected
        }

        $this->assertCount(2, $this->repository->all(), 'earlier successful saves in the batch must not be lost');
        $this->assertNull($this->repository->find('member_suspended', 'email'), 'the failed save must not have been recorded');
    }

    public function testFindHydratesIsDefaultTrueForARowInsertedDirectlyLikeADefaultSeedingMigration(): void
    {
        // Mirrors exactly how database/migrations/009_create_notification_templates_table.php
        // seeds its default rows: a direct $wpdb->insert() bypassing
        // this repository, with is_default explicitly set to 1.
        $this->wpdb->insert('wp_am_notification_templates', [
            'event_key'  => 'admin_new_member',
            'channel'    => 'email',
            'subject'    => 'New member registration',
            'body'       => 'English default body',
            'is_default' => 1,
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $found = $this->repository->find('admin_new_member', 'email');

        $this->assertTrue($found->isDefault);
    }

    public function testSaveAlwaysPersistsIsDefaultFalseRegardlessOfWhatIsPassedIn(): void
    {
        $this->repository->save(new NotificationTemplate(null, 'admin_new_member', 'email', 'Subject', 'Body', isDefault: true));
        $this->assertFalse($this->repository->find('admin_new_member', 'email')->isDefault);

        $this->wpdb->insert('wp_am_notification_templates', [
            'event_key'  => 'member_activated',
            'channel'    => 'email',
            'subject'    => 'Subject',
            'body'       => 'Body',
            'is_default' => 1,
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $this->assertTrue($this->repository->find('member_activated', 'email')->isDefault);

        $this->repository->save(new NotificationTemplate(null, 'member_activated', 'email', 'Subject v2', 'Body v2', isDefault: true));
        $this->assertFalse($this->repository->find('member_activated', 'email')->isDefault, 'updating a row that was is_default = 1 must clear it');
    }

    /**
     * See CertificateTemplateRepositoryTest's identical test for the
     * full reasoning - models a row from before is_default existed at
     * all, the state a legacy database is in until additive migration
     * 025_add_is_default_to_notification_templates_table.php backfills
     * the column on a real server.
     */
    public function testFindTreatsARowMissingTheIsDefaultKeyEntirelyAsNotDefault(): void
    {
        $this->wpdb->insert('wp_am_notification_templates', [
            'event_key'  => 'admin_new_member',
            'channel'    => 'email',
            'subject'    => 'Administrator-edited before is_default existed',
            'body'       => 'legacy content',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $found = $this->repository->find('admin_new_member', 'email');

        $this->assertFalse($found->isDefault);

        $this->repository->save($found->withContent('Still administrator content', 'unchanged'));

        $this->assertFalse($this->repository->find('admin_new_member', 'email')->isDefault);
    }
}
