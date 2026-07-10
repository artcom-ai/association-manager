<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Fields\Repositories;

use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Tests\Support\TestCase;

final class FieldValueRepositoryTest extends TestCase
{
    private FieldValueRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FieldValueRepository();
    }

    public function testDeleteForFieldRemovesTheValueAcrossEveryEntity(): void
    {
        $this->repository->set('member', 1, 'specialty', 'Cardiology');
        $this->repository->set('member', 2, 'specialty', 'Oncology');
        $this->repository->set('member', 1, 'workplace', 'General Hospital');

        $this->repository->deleteForField('member', 'specialty');

        $this->assertNull($this->repository->get('member', 1, 'specialty'));
        $this->assertNull($this->repository->get('member', 2, 'specialty'));
        $this->assertSame('General Hospital', $this->repository->get('member', 1, 'workplace'));
    }

    public function testDeleteForFieldIsScopedToTheGivenEntityType(): void
    {
        $this->repository->set('member', 1, 'specialty', 'Cardiology');
        $this->repository->set('event', 1, 'specialty', 'Not a member field');

        $this->repository->deleteForField('member', 'specialty');

        $this->assertNull($this->repository->get('member', 1, 'specialty'));
        $this->assertSame('Not a member field', $this->repository->get('event', 1, 'specialty'));
    }

    public function testDeleteForFieldOnANonExistentFieldIsANoOp(): void
    {
        $this->repository->set('member', 1, 'specialty', 'Cardiology');

        $this->repository->deleteForField('member', 'nonexistent');

        $this->assertSame('Cardiology', $this->repository->get('member', 1, 'specialty'));
    }
}
