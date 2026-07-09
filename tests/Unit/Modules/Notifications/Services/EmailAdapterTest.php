<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications\Services;

use AssociationManager\Modules\Notifications\Services\EmailAdapter;
use AssociationManager\Tests\Support\TestCase;

final class EmailAdapterTest extends TestCase
{
    public function testSendDelegatesToWpMailAndReturnsItsResult(): void
    {
        $adapter = new EmailAdapter();

        $result = $adapter->send('jane@example.test', 'Subject', 'Message');

        $this->assertTrue($result);
        $this->assertCount(1, $this->sentMail());
        $this->assertSame('jane@example.test', $this->sentMail()[0]['to']);
        $this->assertSame('Subject', $this->sentMail()[0]['subject']);
        $this->assertSame('Message', $this->sentMail()[0]['message']);
    }

    public function testSendReturnsFalseWhenWpMailFails(): void
    {
        $this->failNextMail();
        $adapter = new EmailAdapter();

        $this->assertFalse($adapter->send('jane@example.test', 'Subject', 'Message'));
    }
}
