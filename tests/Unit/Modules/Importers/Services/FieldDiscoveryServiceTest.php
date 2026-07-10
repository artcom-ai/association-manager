<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Importers\Services;

use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepository;
use AssociationManager\Modules\Importers\Domain\FieldMapping;
use AssociationManager\Modules\Importers\Repositories\FieldMappingRepository;
use AssociationManager\Modules\Importers\Services\FieldDiscoveryService;
use AssociationManager\Tests\Support\TestCase;

final class FieldDiscoveryServiceTest extends TestCase
{
    private FieldDefinitionRepository $fieldDefinitions;
    private FieldMappingRepository $fieldMappings;
    private FieldDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fieldDefinitions = new FieldDefinitionRepository();
        $this->fieldMappings = new FieldMappingRepository();
        $this->service = new FieldDiscoveryService($this->fieldDefinitions, $this->fieldMappings);
    }

    public function testCreatesAFieldAndMappingForEachUnmappedKey(): void
    {
        $created = $this->service->createFieldsFromUnmappedKeys('memberpress', ['mepr_eidikotita', 'mepr_foreas']);

        $this->assertSame(2, $created);

        $specialty = $this->fieldDefinitions->find('member', 'mepr_eidikotita');
        $this->assertNotNull($specialty);
        $this->assertSame('Eidikotita', $specialty->label);
        $this->assertSame('text', $specialty->type);
        $this->assertSame('admin', $specialty->visibility);

        $mapping = $this->fieldMappings->find('memberpress', 'mepr_eidikotita');
        $this->assertNotNull($mapping);
        $this->assertSame('mepr_eidikotita', $mapping->sourceKey);
        $this->assertSame('mepr_eidikotita', $mapping->targetFieldKey);
    }

    public function testHyphensInTheSourceKeyBecomeUnderscoresInTheFieldKey(): void
    {
        $this->service->createFieldsFromUnmappedKeys('memberpress', ['mepr-tilefono-epikoinonias']);

        // Hyphens are converted, not stripped - "mepr-tilefono-epikoinonias"
        // must not collapse into an unreadable "meprtilefonoepikoinonias".
        $field = $this->fieldDefinitions->find('member', 'mepr_tilefono_epikoinonias');

        $this->assertNotNull($field);
        $this->assertSame('Tilefono Epikoinonias', $field->label);

        $mapping = $this->fieldMappings->find('memberpress', 'mepr-tilefono-epikoinonias');
        $this->assertNotNull($mapping);
        $this->assertSame('mepr_tilefono_epikoinonias', $mapping->targetFieldKey);
    }

    public function testSkipsAKeyThatAlreadyHasAMapping(): void
    {
        $this->fieldMappings->save('memberpress', new FieldMapping('mepr_eidikotita', 'specialty'));

        $created = $this->service->createFieldsFromUnmappedKeys('memberpress', ['mepr_eidikotita']);

        $this->assertSame(0, $created);
        // The pre-existing mapping's own target field was never touched.
        $this->assertNull($this->fieldDefinitions->find('member', 'mepr_eidikotita'));
    }

    public function testRunningTwiceDoesNotCreateDuplicateFieldsOrMappings(): void
    {
        $this->service->createFieldsFromUnmappedKeys('memberpress', ['mepr_eidikotita']);
        $secondRunCreated = $this->service->createFieldsFromUnmappedKeys('memberpress', ['mepr_eidikotita']);

        $this->assertSame(0, $secondRunCreated);
        $this->assertCount(1, $this->fieldDefinitions->all('member'));
        $this->assertCount(1, $this->fieldMappings->all('memberpress'));
    }

    public function testSkipsAKeyThatSanitizesToAnEmptyFieldKey(): void
    {
        $created = $this->service->createFieldsFromUnmappedKeys('memberpress', ['@@@']);

        $this->assertSame(0, $created);
    }
}
