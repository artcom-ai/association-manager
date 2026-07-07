<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Fields\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Tests\Support\TestCase;

final class FieldValidatorTest extends TestCase
{
    private FieldValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FieldValidator();
    }

    public function testRequiredFieldRejectsEmptyValue(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_TEXT, required: true);

        $this->assertCount(1, $this->validator->validate($field, null));
        $this->assertCount(1, $this->validator->validate($field, ''));
    }

    public function testOptionalEmptyValueProducesNoErrors(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_TEXT, required: false);

        $this->assertSame([], $this->validator->validate($field, null));
    }

    public function testTextLengthBounds(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_TEXT, minLength: 3, maxLength: 5);

        $this->assertCount(1, $this->validator->validate($field, 'ab'));
        $this->assertSame([], $this->validator->validate($field, 'abcd'));
        $this->assertCount(1, $this->validator->validate($field, 'abcdef'));
    }

    public function testNumberBounds(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_NUMBER, minValue: 1, maxValue: 10);

        $this->assertCount(1, $this->validator->validate($field, 'not-a-number'));
        $this->assertCount(1, $this->validator->validate($field, 0));
        $this->assertSame([], $this->validator->validate($field, 5));
        $this->assertCount(1, $this->validator->validate($field, 11));
    }

    public function testDateMustBeParseable(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_DATE);

        $this->assertSame([], $this->validator->validate($field, '2026-01-01'));
        $this->assertCount(1, $this->validator->validate($field, 'not-a-date'));
    }

    public function testSelectMustBeAnAllowedOption(): void
    {
        $field = new FieldDefinition(
            key: 'x',
            label: 'X',
            type: FieldDefinition::TYPE_SELECT,
            options: ['a' => 'Option A', 'b' => 'Option B'],
        );

        $this->assertSame([], $this->validator->validate($field, 'a'));
        $this->assertCount(1, $this->validator->validate($field, 'c'));
    }

    public function testCheckboxAcceptsBooleanish(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_CHECKBOX);

        $this->assertSame([], $this->validator->validate($field, '1'));
        $this->assertSame([], $this->validator->validate($field, '0'));
        $this->assertCount(1, $this->validator->validate($field, 'yes'));
    }

    public function testFileMustBeAPositiveInteger(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_FILE);

        $this->assertSame([], $this->validator->validate($field, '42'));
        $this->assertCount(1, $this->validator->validate($field, '0'));
        $this->assertCount(1, $this->validator->validate($field, 'not-numeric'));
    }

    public function testLocationAcceptsValidLatLng(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_LOCATION);

        $this->assertSame([], $this->validator->validate($field, '37.9838,23.7275'));
    }

    public function testLocationRejectsMalformedString(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_LOCATION);

        $this->assertCount(1, $this->validator->validate($field, 'not-a-location'));
    }

    public function testLocationRejectsOutOfRangeCoordinates(): void
    {
        $field = new FieldDefinition(key: 'x', label: 'X', type: FieldDefinition::TYPE_LOCATION);

        $this->assertCount(1, $this->validator->validate($field, '200,300'));
    }
}
