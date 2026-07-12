<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Importers\Repositories;

use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Modules\Importers\Domain\FieldMapping;
use AssociationManager\Modules\Importers\Repositories\FieldMappingRepository;
use AssociationManager\Tests\Support\TestCase;

final class FieldMappingRepositoryTest extends TestCase
{
    private FieldMappingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FieldMappingRepository();
    }

    public function testSaveThenFindRoundTrips(): void
    {
        $this->repository->save('memberpress', new FieldMapping('mepr_eidikotita', 'specialty'));

        $found = $this->repository->find('memberpress', 'mepr_eidikotita');

        $this->assertNotNull($found);
        $this->assertSame('mepr_eidikotita', $found->sourceKey);
        $this->assertSame('specialty', $found->targetFieldKey);
    }

    public function testFindReturnsNullWhenNothingIsMapped(): void
    {
        $this->assertNull($this->repository->find('memberpress', 'unmapped_key'));
    }

    public function testSaveTwiceUpdatesRatherThanDuplicating(): void
    {
        $this->repository->save('memberpress', new FieldMapping('mepr_eidikotita', 'specialty'));
        $this->repository->save('memberpress', new FieldMapping('mepr_eidikotita', 'renamed_field'));

        $all = $this->repository->all('memberpress');

        $this->assertCount(1, $all);
        $this->assertSame('renamed_field', $all[0]->targetFieldKey);
    }

    public function testAllIsScopedToTheGivenSourceSystem(): void
    {
        $this->repository->save('memberpress', new FieldMapping('a', 'a_field'));
        $this->repository->save('other-source', new FieldMapping('b', 'b_field'));

        $this->assertCount(1, $this->repository->all('memberpress'));
        $this->assertCount(1, $this->repository->all('other-source'));
    }

    public function testAllSourceSystemsReturnsEachDistinctSourceOnce(): void
    {
        $this->repository->save('memberpress', new FieldMapping('a', 'a_field'));
        $this->repository->save('memberpress', new FieldMapping('b', 'b_field'));
        $this->repository->save('other-source', new FieldMapping('c', 'c_field'));

        $sources = $this->repository->allSourceSystems();

        $this->assertCount(2, $sources);
        $this->assertContains('memberpress', $sources);
        $this->assertContains('other-source', $sources);
    }

    public function testSaveThrowsWhenInsertFails(): void
    {
        $this->wpdb->failNextInsert('wp_am_field_mappings');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save('memberpress', new FieldMapping('mepr_eidikotita', 'specialty'));
    }

    public function testSaveThrowsWhenUpdateFails(): void
    {
        $this->repository->save('memberpress', new FieldMapping('mepr_eidikotita', 'specialty'));

        $this->wpdb->failNextUpdate('wp_am_field_mappings');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save('memberpress', new FieldMapping('mepr_eidikotita', 'renamed_field'));
    }
}
