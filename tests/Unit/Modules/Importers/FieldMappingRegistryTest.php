<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Importers;

use AssociationManager\Modules\Importers\Domain\FieldMapping;
use AssociationManager\Modules\Importers\FieldMappingRegistry;
use PHPUnit\Framework\TestCase;

final class FieldMappingRegistryTest extends TestCase
{
    public function testForSourceReturnsOnlyMappingsRegisteredForThatSource(): void
    {
        $registry = new FieldMappingRegistry();
        $registry->register('memberpress', new FieldMapping('mepr-specialty', 'specialty'));
        $registry->register('memberpress', new FieldMapping('mepr-afm', 'afm'));
        $registry->register('other-source', new FieldMapping('some-key', 'some_field'));

        $mappings = $registry->forSource('memberpress');

        $this->assertCount(2, $mappings);
        $this->assertSame(['mepr-specialty', 'mepr-afm'], array_map(static fn (FieldMapping $m): string => $m->sourceKey, $mappings));
    }

    public function testForSourceReturnsEmptyArrayForUnknownSource(): void
    {
        $registry = new FieldMappingRegistry();

        $this->assertSame([], $registry->forSource('nothing-registered'));
    }

    public function testRegisteringTheSameSourceKeyTwiceOverwritesTheMapping(): void
    {
        $registry = new FieldMappingRegistry();
        $registry->register('memberpress', new FieldMapping('mepr-specialty', 'specialty'));
        $registry->register('memberpress', new FieldMapping('mepr-specialty', 'workplace'));

        $mappings = $registry->forSource('memberpress');

        $this->assertCount(1, $mappings);
        $this->assertSame('workplace', $mappings[0]->targetFieldKey);
    }

    public function testApplyUsesTheTransformClosureWhenGiven(): void
    {
        $mapping = new FieldMapping('mepr-afm', 'afm', static fn (mixed $value): string => trim((string) $value));

        $this->assertSame('12345', $mapping->apply(' 12345 '));
    }

    public function testApplyReturnsTheRawValueWithoutATransform(): void
    {
        $mapping = new FieldMapping('mepr-specialty', 'specialty');

        $this->assertSame('Cardiology', $mapping->apply('Cardiology'));
    }
}
