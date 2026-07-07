<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Notifications;

use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;
use AssociationManager\Modules\Notifications\NotificationsModule;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepository;
use AssociationManager\Modules\Notifications\Services\NotificationDispatcher;
use AssociationManager\Modules\Notifications\Services\NotificationTemplateRenderer;
use AssociationManager\Tests\Support\TestCase;
use ReflectionMethod;

/**
 * NotificationsModule wires its handlers via add_action(), which is a
 * no-op stub in this test environment (there is no real WordPress hook
 * dispatcher to exercise). These tests invoke the module's private
 * handler methods directly via reflection instead of re-implementing
 * their branching logic, so the actual production code path (event key
 * mapping, recipient resolution) is what gets verified.
 */
final class NotificationsModuleTest extends TestCase
{
    private NotificationsModule $module;
    private NotificationTemplateRepository $templates;
    private NotificationQueueRepository $queue;
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = new NotificationsModule();
        $this->templates = new NotificationTemplateRepository();
        $this->queue = new NotificationQueueRepository();

        $this->dispatcher = new NotificationDispatcher(
            $this->templates,
            $this->queue,
            new NotificationTemplateRenderer(),
        );

        $this->setNow('2026-01-01 00:00:00');

        foreach (['admin_new_member', 'member_activated', 'member_suspended', 'member_archived', 'membership_renewed'] as $eventKey) {
            $this->templates->save(new NotificationTemplate(
                null,
                $eventKey,
                NotificationChannel::EMAIL,
                "Subject for {$eventKey}",
                "Body for {member_number}"
            ));
        }
    }

    public function testNewMemberNotifiesAdminAddress(): void
    {
        $this->setOption('admin_email', 'admin@example.test');
        $member = new Member(1, 'uuid-1', null, 'M-001', null, 'candidate', 'individual', null, null, null);

        $this->invokeHandleMemberStatusChanged($member, null, 'candidate');

        $due = $this->queue->findDue('2026-01-01 00:00:00');
        $this->assertCount(1, $due);
        $this->assertSame('admin_new_member', $due[0]->eventKey);
        $this->assertSame('admin@example.test', $due[0]->recipient);
    }

    public function testActivatedSuspendedArchivedMapToExpectedEventKeys(): void
    {
        $member = new Member(1, 'uuid-1', null, 'M-001', 'jane@example.test', 'active', 'individual', null, null, null);

        $this->invokeHandleMemberStatusChanged($member, 'candidate', 'active');
        $this->invokeHandleMemberStatusChanged($member, 'active', 'suspended');
        $this->invokeHandleMemberStatusChanged($member, 'suspended', 'inactive');

        $due = $this->queue->findDue('2026-01-01 00:00:00');
        $eventKeys = array_map(static fn ($n) => $n->eventKey, $due);

        $this->assertContains('member_activated', $eventKeys);
        $this->assertContains('member_suspended', $eventKeys);
        $this->assertContains('member_archived', $eventKeys);
    }

    public function testStatusWithNoMappedEventKeyDoesNotEnqueue(): void
    {
        $member = new Member(1, 'uuid-1', null, 'M-001', 'jane@example.test', 'expired', 'individual', null, null, null);

        $this->invokeHandleMemberStatusChanged($member, 'active', 'expired');

        $this->assertCount(0, $this->queue->findDue('2026-01-01 00:00:00'));
    }

    public function testResolveMemberEmailPrefersOwnEmailOverWpAccount(): void
    {
        $this->setUserEmail(5, 'wp-account@example.test');
        $member = new Member(1, 'uuid-1', 5, 'M-001', 'own@example.test', 'active', 'individual', null, null, null);

        $this->assertSame('own@example.test', $this->invokeResolveMemberEmail($member));
    }

    public function testResolveMemberEmailFallsBackToWpAccountWhenOwnEmailIsNull(): void
    {
        $this->setUserEmail(5, 'wp-account@example.test');
        $member = new Member(1, 'uuid-1', 5, 'M-001', null, 'active', 'individual', null, null, null);

        $this->assertSame('wp-account@example.test', $this->invokeResolveMemberEmail($member));
    }

    public function testResolveMemberEmailReturnsNullWhenNeitherIsPresent(): void
    {
        $member = new Member(1, 'uuid-1', null, 'M-001', null, 'active', 'individual', null, null, null);

        $this->assertNull($this->invokeResolveMemberEmail($member));
    }

    private function invokeHandleMemberStatusChanged(Member $member, ?string $oldStatus, string $newStatus): void
    {
        $method = new ReflectionMethod(NotificationsModule::class, 'handleMemberStatusChanged');
        $method->setAccessible(true);
        $method->invoke($this->module, $this->dispatcher, $member, $oldStatus, $newStatus);
    }

    private function invokeResolveMemberEmail(Member $member): ?string
    {
        $method = new ReflectionMethod(NotificationsModule::class, 'resolveMemberEmail');
        $method->setAccessible(true);

        return $method->invoke($this->module, $member);
    }
}
