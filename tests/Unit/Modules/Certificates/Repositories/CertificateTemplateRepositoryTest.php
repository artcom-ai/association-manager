<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Certificates\Repositories;

use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepository;
use AssociationManager\Tests\Support\TestCase;

final class CertificateTemplateRepositoryTest extends TestCase
{
    private CertificateTemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CertificateTemplateRepository();
    }

    public function testSaveInsertsWhenNotExisting(): void
    {
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>'));

        $found = $this->repository->find('membership');
        $this->assertNotNull($found);
        $this->assertSame('Membership', $found->name);
    }

    public function testSaveUpdatesWhenAlreadyExisting(): void
    {
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>'));
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership v2', '<p>y</p>'));

        $all = $this->repository->all();
        $this->assertCount(1, $all);
        $this->assertSame('Membership v2', $all[0]->name);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->find('no-such-type'));
    }

    public function testSaveThrowsWhenInsertFails(): void
    {
        $this->wpdb->failNextInsert('wp_am_certificate_templates');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>'));
    }

    public function testSaveThrowsWhenUpdateFails(): void
    {
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>'));

        $this->wpdb->failNextUpdate('wp_am_certificate_templates');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership v2', '<p>y</p>'));
    }

    public function testFindHydratesIsDefaultTrueForARowInsertedDirectlyLikeADefaultSeedingMigration(): void
    {
        // Mirrors exactly how database/migrations/012_create_certificate_templates_table.php
        // seeds its default row: a direct $wpdb->insert() bypassing this
        // repository, with is_default explicitly set to 1.
        $this->wpdb->insert('wp_am_certificate_templates', [
            'type_key'   => 'membership',
            'name'       => 'Membership Certificate',
            'html_body'  => '<html>English default</html>',
            'is_default' => 1,
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $found = $this->repository->find('membership');

        $this->assertTrue($found->isDefault);
    }

    public function testSaveAlwaysPersistsIsDefaultFalseRegardlessOfWhatIsPassedIn(): void
    {
        // Passing isDefault: true here must not matter - save() is the
        // "someone is deliberately writing this" path and always forces
        // it to false, whether inserting or updating.
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>', isDefault: true));
        $this->assertFalse($this->repository->find('membership')->isDefault);

        $this->wpdb->insert('wp_am_certificate_templates', [
            'type_key'   => 'other',
            'name'       => 'Other',
            'html_body'  => '<p>English default</p>',
            'is_default' => 1,
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $this->assertTrue($this->repository->find('other')->isDefault);

        $this->repository->save(new CertificateTemplate(null, 'other', 'Other v2', '<p>y</p>', isDefault: true));
        $this->assertFalse($this->repository->find('other')->isDefault, 'updating a row that was is_default = 1 must clear it');
    }

    /**
     * Models a row from BEFORE the is_default column existed at all -
     * the exact state a legacy database is in until additive migration
     * 026_add_is_default_to_certificate_templates_table.php backfills
     * the column (dbDelta() is a no-op stub in this test environment,
     * so that migration's real ALTER TABLE effect can't be exercised
     * here - see docs/adr/024-elesyth-implementation-migrations.md's
     * third addendum). This proves the repository's own read path
     * degrades safely (isDefault = false, the conservative
     * "don't overwrite" choice) even for a row entirely missing the
     * column, and that a subsequent deliberate save() persists a real
     * is_default = 0 going forward - no unknown-key/undefined-index
     * PHP error either way.
     */
    public function testFindTreatsARowMissingTheIsDefaultKeyEntirelyAsNotDefault(): void
    {
        $this->wpdb->insert('wp_am_certificate_templates', [
            'type_key'   => 'membership',
            'name'       => 'Administrator-edited before is_default existed',
            'html_body'  => '<p>legacy content</p>',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $found = $this->repository->find('membership');

        $this->assertFalse($found->isDefault);

        $this->repository->save($found->withContent('Still administrator content', '<p>unchanged</p>'));

        $this->assertFalse($this->repository->find('membership')->isDefault);
    }
}
