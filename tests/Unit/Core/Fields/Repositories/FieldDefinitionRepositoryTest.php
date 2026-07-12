<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Fields\Repositories;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepository;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Tests\Support\TestCase;

final class FieldDefinitionRepositoryTest extends TestCase
{
    private FieldDefinitionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FieldDefinitionRepository();
    }

    public function testSaveThenFindRoundTripsEveryProperty(): void
    {
        $field = new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_SELECT,
            required: true,
            options: ['a' => 'Option A', 'b' => 'Option B'],
            minLength: 2,
            maxLength: 190,
            minValue: 1.5,
            maxValue: 99.5,
            helpText: 'Pick one',
            order: 10,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
            showInList: true,
        );

        $this->repository->save('member', $field);
        $found = $this->repository->find('member', 'specialty');

        $this->assertNotNull($found);
        $this->assertSame('specialty', $found->key);
        $this->assertSame('Specialty', $found->label);
        $this->assertSame(FieldDefinition::TYPE_SELECT, $found->type);
        $this->assertTrue($found->required);
        $this->assertSame(['a' => 'Option A', 'b' => 'Option B'], $found->options);
        $this->assertSame(2, $found->minLength);
        $this->assertSame(190, $found->maxLength);
        $this->assertSame(1.5, $found->minValue);
        $this->assertSame(99.5, $found->maxValue);
        $this->assertSame('Pick one', $found->helpText);
        $this->assertSame(10, $found->order);
        $this->assertSame(FieldDefinition::VISIBILITY_PRIVATE, $found->visibility);
        $this->assertTrue($found->showInList);
    }

    public function testShowInListDefaultsToFalse(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'notes', label: 'Notes', type: FieldDefinition::TYPE_TEXT));

        $found = $this->repository->find('member', 'notes');

        $this->assertNotNull($found);
        $this->assertFalse($found->showInList);
    }

    public function testSaveWithoutOptionalPropertiesRoundTripsNulls(): void
    {
        $field = new FieldDefinition(key: 'notes', label: 'Notes', type: FieldDefinition::TYPE_TEXT);

        $this->repository->save('member', $field);
        $found = $this->repository->find('member', 'notes');

        $this->assertNotNull($found);
        $this->assertNull($found->options);
        $this->assertNull($found->minLength);
        $this->assertNull($found->maxLength);
        $this->assertNull($found->minValue);
        $this->assertNull($found->maxValue);
        $this->assertNull($found->helpText);
    }

    public function testFindReturnsNullForAnUnknownKey(): void
    {
        $this->assertNull($this->repository->find('member', 'nope'));
    }

    public function testSaveTwiceUpdatesRatherThanDuplicating(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'specialty', label: 'Specialty', type: FieldDefinition::TYPE_TEXT));
        $this->repository->save('member', new FieldDefinition(key: 'specialty', label: 'Ειδικότητα', type: FieldDefinition::TYPE_TEXT));

        $all = $this->repository->all('member');

        $this->assertCount(1, $all);
        $this->assertSame('Ειδικότητα', $all[0]->label);
    }

    public function testAllOrdersByDisplayOrderThenId(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'second', label: 'Second', type: FieldDefinition::TYPE_TEXT, order: 20));
        $this->repository->save('member', new FieldDefinition(key: 'first', label: 'First', type: FieldDefinition::TYPE_TEXT, order: 10));

        $all = $this->repository->all('member');

        $this->assertSame(['first', 'second'], array_map(static fn (FieldDefinition $f): string => $f->key, $all));
    }

    public function testAllIsScopedToTheGivenEntityType(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'a', label: 'A', type: FieldDefinition::TYPE_TEXT));
        $this->repository->save('event', new FieldDefinition(key: 'b', label: 'B', type: FieldDefinition::TYPE_TEXT));

        $this->assertCount(1, $this->repository->all('member'));
        $this->assertCount(1, $this->repository->all('event'));
    }

    public function testDeleteRemovesTheField(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'specialty', label: 'Specialty', type: FieldDefinition::TYPE_TEXT));

        $this->repository->delete('member', 'specialty');

        $this->assertNull($this->repository->find('member', 'specialty'));
    }

    public function testAllEntityTypesReturnsEachDistinctEntityTypeOnce(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'a', label: 'A', type: FieldDefinition::TYPE_TEXT));
        $this->repository->save('member', new FieldDefinition(key: 'b', label: 'B', type: FieldDefinition::TYPE_TEXT));
        $this->repository->save('event', new FieldDefinition(key: 'c', label: 'C', type: FieldDefinition::TYPE_TEXT));

        $entityTypes = $this->repository->allEntityTypes();

        $this->assertCount(2, $entityTypes);
        $this->assertContains('member', $entityTypes);
        $this->assertContains('event', $entityTypes);
    }

    public function testSaveThrowsWhenInsertFails(): void
    {
        $this->wpdb->failNextInsert('wp_am_field_definitions');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save('member', new FieldDefinition(key: 'specialty', label: 'Specialty', type: FieldDefinition::TYPE_TEXT));
    }

    public function testSaveThrowsWhenUpdateFails(): void
    {
        $this->repository->save('member', new FieldDefinition(key: 'specialty', label: 'Specialty', type: FieldDefinition::TYPE_TEXT));

        $this->wpdb->failNextUpdate('wp_am_field_definitions');

        $this->expectException(DatabaseWriteException::class);

        $this->repository->save('member', new FieldDefinition(key: 'specialty', label: 'Ειδικότητα', type: FieldDefinition::TYPE_TEXT));
    }
}
