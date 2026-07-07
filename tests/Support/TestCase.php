<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Support;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for every test: fresh FakeWpdb and reset bootstrap.php
 * global test state before each test, so tests never leak state into
 * one another.
 */
abstract class TestCase extends BaseTestCase
{
    protected FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $GLOBALS['__am_test_now'] = '2026-01-01 00:00:00';
        $GLOBALS['__am_test_current_user_id'] = 0;
        $GLOBALS['__am_test_current_user_can'] = true;
        $GLOBALS['__am_test_logged_in'] = false;
        $GLOBALS['__am_test_fired_actions'] = [];
        $GLOBALS['__am_test_scheduled_hooks'] = [];
        $GLOBALS['__am_test_transients'] = [];
        $GLOBALS['__am_test_uuid_counter'] = 0;
        $GLOBALS['__am_test_media_upload_result'] = 1;
        $GLOBALS['__am_test_last_redirect'] = null;
    }

    protected function setNow(string $mysqlDateTime): void
    {
        $GLOBALS['__am_test_now'] = $mysqlDateTime;
    }

    protected function setCurrentUserId(int $id): void
    {
        $GLOBALS['__am_test_current_user_id'] = $id;
    }

    protected function setLoggedIn(bool $loggedIn): void
    {
        $GLOBALS['__am_test_logged_in'] = $loggedIn;
    }

    /**
     * @return array<int, array{hook: string, args: array<int, mixed>}>
     */
    protected function firedActions(): array
    {
        return $GLOBALS['__am_test_fired_actions'];
    }

    /**
     * @return array<int, array{hook: string, args: array<int, mixed>}>
     */
    protected function firedActionsNamed(string $hook): array
    {
        return array_values(array_filter(
            $this->firedActions(),
            static fn (array $action): bool => $action['hook'] === $hook
        ));
    }
}
