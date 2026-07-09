<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core;

use AssociationManager\Core\Visibility;
use AssociationManager\Tests\Support\TestCase;

final class VisibilityTest extends TestCase
{
    public function testPublicIsVisibleToEveryLevel(): void
    {
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_PUBLIC, Visibility::VISIBILITY_PUBLIC));
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_PUBLIC, Visibility::VISIBILITY_PRIVATE));
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_PUBLIC, Visibility::VISIBILITY_ADMIN));
    }

    public function testPrivateIsHiddenFromPublicOnly(): void
    {
        $this->assertFalse(Visibility::isAtLeast(Visibility::VISIBILITY_PRIVATE, Visibility::VISIBILITY_PUBLIC));
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_PRIVATE, Visibility::VISIBILITY_PRIVATE));
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_PRIVATE, Visibility::VISIBILITY_ADMIN));
    }

    public function testAdminIsVisibleOnlyToAdmin(): void
    {
        $this->assertFalse(Visibility::isAtLeast(Visibility::VISIBILITY_ADMIN, Visibility::VISIBILITY_PUBLIC));
        $this->assertFalse(Visibility::isAtLeast(Visibility::VISIBILITY_ADMIN, Visibility::VISIBILITY_PRIVATE));
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_ADMIN, Visibility::VISIBILITY_ADMIN));
    }

    public function testUnknownLevelDefaultsToAdminForContentAndPublicForViewer(): void
    {
        $this->assertFalse(Visibility::isAtLeast('bogus', Visibility::VISIBILITY_PUBLIC));
        $this->assertTrue(Visibility::isAtLeast(Visibility::VISIBILITY_PUBLIC, 'bogus'));
    }
}
