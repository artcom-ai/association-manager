<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Services\NotificationTemplateRenderer;
use AssociationManager\Tests\Support\TestCase;

final class NotificationTemplateRendererTest extends TestCase
{
    private NotificationTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new NotificationTemplateRenderer();
    }

    public function testSubstitutesPlaceholders(): void
    {
        $result = $this->renderer->render(
            'Hello {first_name}, your member number is {member_number}.',
            ['first_name' => 'Jane', 'member_number' => 'M-001']
        );

        $this->assertSame('Hello Jane, your member number is M-001.', $result);
    }

    public function testUnmatchedTokenIsLeftAsIs(): void
    {
        $result = $this->renderer->render('Hello {first_name}, welcome to {unknown_token}.', ['first_name' => 'Jane']);

        $this->assertSame('Hello Jane, welcome to {unknown_token}.', $result);
    }

    public function testNoPlaceholdersReturnsTextUnchanged(): void
    {
        $result = $this->renderer->render('Plain text with no tokens.', []);

        $this->assertSame('Plain text with no tokens.', $result);
    }
}
