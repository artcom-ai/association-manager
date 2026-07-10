<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Fields\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Tests\Support\TestCase;

final class FieldValueServiceTest extends TestCase
{
    private FieldRegistry $registry;
    private FieldValueService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new FieldRegistry();
        $this->service = new FieldValueService($this->registry, new FieldValueRepository(), new FieldValidator());

        $this->registry->register('member', new FieldDefinition(
            key: 'education',
            label: 'Education',
            type: FieldDefinition::TYPE_SELECT,
            required: true,
            options: ['bachelor' => 'Bachelor', 'phd' => 'PhD'],
        ));
        $this->registry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
        ));
    }

    public function testValidateReturnsErrorsForInvalidValuesOnly(): void
    {
        $errors = $this->service->validate('member', ['education' => 'not-an-option']);

        $this->assertArrayHasKey('education', $errors);
    }

    public function testUnknownFieldKeysAreSilentlyIgnored(): void
    {
        $errors = $this->service->validate('member', ['not_a_registered_field' => 'anything']);

        $this->assertSame([], $errors);
    }

    public function testSaveThrowsOnInvalidValueAndPersistsNothing(): void
    {
        $this->expectException(FieldValidationException::class);

        $this->service->save('member', 1, ['education' => 'invalid']);
    }

    public function testSaveThenValuesForRoundTrips(): void
    {
        $this->service->save('member', 1, ['education' => 'phd', 'specialty' => 'Ψυχοθεραπεία']);

        $values = $this->service->valuesFor('member', 1);

        $this->assertSame('phd', $values['education']);
        $this->assertSame('Ψυχοθεραπεία', $values['specialty']);
    }

    public function testPartialSaveLeavesOtherFieldsUntouched(): void
    {
        $this->service->save('member', 1, ['education' => 'phd', 'specialty' => 'Original']);

        $this->service->save('member', 1, ['specialty' => 'Updated']);

        $values = $this->service->valuesFor('member', 1);
        $this->assertSame('phd', $values['education'], 'untouched field must survive a partial save');
        $this->assertSame('Updated', $values['specialty']);
    }

    public function testSavingNullClearsAnExistingValue(): void
    {
        $this->service->save('member', 1, ['specialty' => 'Something']);

        $this->service->save('member', 1, ['specialty' => null]);

        $this->assertArrayNotHasKey('specialty', $this->service->valuesFor('member', 1));
    }

    public function testHandleFileUploadStoresTheAttachmentId(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
        ));

        $GLOBALS['__am_test_media_upload_result'] = 4242;

        $attachmentId = $this->service->handleFileUpload('member', 1, 'id_document', [
            'name' => 'id.pdf',
            'type' => 'application/pdf',
            'tmp_name' => '/tmp/fake',
            'error' => 0,
            'size' => 123,
        ]);

        $this->assertSame(4242, $attachmentId);
        $this->assertSame('4242', $this->service->valuesFor('member', 1)['id_document']);
    }

    public function testSaveWithUploadsHandlesAMixOfTextAndFileFields(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
        ));

        $GLOBALS['__am_test_media_upload_result'] = 99;

        $this->service->saveWithUploads(
            'member',
            1,
            ['specialty' => 'Cardiology'],
            [
                'name' => ['id_document' => 'diploma.pdf'],
                'type' => ['id_document' => 'application/pdf'],
                'tmp_name' => ['id_document' => '/tmp/fake'],
                'error' => ['id_document' => 0],
                'size' => ['id_document' => 456],
            ]
        );

        $values = $this->service->valuesFor('member', 1);
        $this->assertSame('Cardiology', $values['specialty']);
        $this->assertSame('99', $values['id_document']);
    }

    public function testSaveWithUploadsIgnoresAnEmptyFileInput(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
        ));

        // Browsers submit an untouched file input as an empty name with
        // UPLOAD_ERR_NO_FILE, not by omitting it - this must not be
        // treated as "upload a file named nothing".
        $this->service->saveWithUploads(
            'member',
            1,
            ['specialty' => 'Cardiology'],
            [
                'name' => ['id_document' => ''],
                'type' => ['id_document' => ''],
                'tmp_name' => ['id_document' => ''],
                'error' => ['id_document' => UPLOAD_ERR_NO_FILE],
                'size' => ['id_document' => 0],
            ]
        );

        $values = $this->service->valuesFor('member', 1);
        $this->assertSame('Cardiology', $values['specialty']);
        $this->assertArrayNotHasKey('id_document', $values);
    }

    public function testSaveWithUploadsToleratesNoFileInputsAtAll(): void
    {
        $this->service->saveWithUploads('member', 1, ['specialty' => 'Cardiology'], null);

        $this->assertSame('Cardiology', $this->service->valuesFor('member', 1)['specialty']);
    }

    public function testSaveWithUploadsStillThrowsForAnInvalidTextField(): void
    {
        $this->expectException(FieldValidationException::class);

        $this->service->saveWithUploads('member', 1, ['education' => 'invalid'], null);
    }

    public function testSaveWithUploadsAppliesTheFirstUploadDirectlyEvenWhenApprovalIsRequired(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            requiresApprovalToChange: true,
        ));

        $GLOBALS['__am_test_media_upload_result'] = 100;

        $pending = $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'first.pdf'));

        $this->assertSame([], $pending, 'the first-ever upload on a field must never require approval');
        $this->assertSame('100', $this->service->valuesFor('member', 1)['id_document']);
    }

    public function testSaveWithUploadsRoutesAReplacementToPendingWhenApprovalIsRequired(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            requiresApprovalToChange: true,
        ));

        $GLOBALS['__am_test_media_upload_result'] = 100;
        $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'first.pdf'));

        $GLOBALS['__am_test_media_upload_result'] = 200;
        $pending = $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'replacement.pdf'));

        $this->assertSame(['id_document'], $pending);
        $this->assertSame('100', $this->service->valuesFor('member', 1)['id_document'], 'the live value must stay the old file until approved');
    }

    public function testSaveWithUploadsBypassApprovalAppliesAReplacementDirectly(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            requiresApprovalToChange: true,
        ));

        $GLOBALS['__am_test_media_upload_result'] = 100;
        $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'first.pdf'));

        $GLOBALS['__am_test_media_upload_result'] = 200;
        $pending = $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'replacement.pdf'), bypassApproval: true);

        $this->assertSame([], $pending, 'an admin-originated save must bypass the approval gate entirely');
        $this->assertSame('200', $this->service->valuesFor('member', 1)['id_document']);
    }

    public function testSaveWithUploadsReplacesDirectlyWhenTheFieldDoesNotRequireApproval(): void
    {
        $this->registry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
        ));

        $GLOBALS['__am_test_media_upload_result'] = 100;
        $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'first.pdf'));

        $GLOBALS['__am_test_media_upload_result'] = 200;
        $pending = $this->service->saveWithUploads('member', 1, [], $this->fileUpload('id_document', 'replacement.pdf'));

        $this->assertSame([], $pending);
        $this->assertSame('200', $this->service->valuesFor('member', 1)['id_document']);
    }

    /**
     * @return array{name: array<string, string>, type: array<string, string>, tmp_name: array<string, string>, error: array<string, int>, size: array<string, int>}
     */
    private function fileUpload(string $fieldKey, string $fileName): array
    {
        return [
            'name' => [$fieldKey => $fileName],
            'type' => [$fieldKey => 'application/pdf'],
            'tmp_name' => [$fieldKey => '/tmp/fake'],
            'error' => [$fieldKey => 0],
            'size' => [$fieldKey => 123],
        ];
    }
}
