<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Fields;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Tests\Support\TestCase;

final class FieldDefinitionTest extends TestCase
{
    public function testPublicFieldIsVisibleToEveryLevel(): void
    {
        $field = new FieldDefinition(
            key: 'website',
            label: 'Website',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PUBLIC,
        );

        $this->assertTrue($field->isVisibleTo(FieldDefinition::VISIBILITY_PUBLIC));
        $this->assertTrue($field->isVisibleTo(FieldDefinition::VISIBILITY_PRIVATE));
        $this->assertTrue($field->isVisibleTo(FieldDefinition::VISIBILITY_ADMIN));
    }

    public function testPrivateFieldIsHiddenFromPublicOnly(): void
    {
        $field = new FieldDefinition(
            key: 'phone',
            label: 'Phone',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        );

        $this->assertFalse($field->isVisibleTo(FieldDefinition::VISIBILITY_PUBLIC));
        $this->assertTrue($field->isVisibleTo(FieldDefinition::VISIBILITY_PRIVATE));
        $this->assertTrue($field->isVisibleTo(FieldDefinition::VISIBILITY_ADMIN));
    }

    public function testAdminFieldIsVisibleOnlyToAdmin(): void
    {
        $field = new FieldDefinition(
            key: 'notes',
            label: 'Notes',
            type: FieldDefinition::TYPE_TEXTAREA,
            visibility: FieldDefinition::VISIBILITY_ADMIN,
        );

        $this->assertFalse($field->isVisibleTo(FieldDefinition::VISIBILITY_PUBLIC));
        $this->assertFalse($field->isVisibleTo(FieldDefinition::VISIBILITY_PRIVATE));
        $this->assertTrue($field->isVisibleTo(FieldDefinition::VISIBILITY_ADMIN));
    }

    public function testVisibilityDefaultsToAdmin(): void
    {
        $field = new FieldDefinition(key: 'legacy', label: 'Legacy', type: FieldDefinition::TYPE_TEXT);

        $this->assertSame(FieldDefinition::VISIBILITY_ADMIN, $field->visibility);
    }
}
