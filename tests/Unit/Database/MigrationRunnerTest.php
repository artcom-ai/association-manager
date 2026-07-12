<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Database;

use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Database\MigrationInterface;
use AssociationManager\Database\MigrationRunner;
use AssociationManager\Tests\Support\TestCase;

final class MigrationRunnerTest extends TestCase
{
    public function testRunsEachMigrationOnceAndSkipsOnRerun(): void
    {
        $runCount = 0;
        $migration = $this->fakeMigration('001_example', function () use (&$runCount): void {
            ++$runCount;
        });

        $runner = new MigrationRunner('am_test_migrations');
        $runner->run([$migration]);
        $runner->run([$migration]);

        $this->assertSame(1, $runCount, 'a migration already recorded as executed must not run again');
    }

    public function testDefaultOptionKeyMatchesCoresExistingBehavior(): void
    {
        $migration = $this->fakeMigration('001_example', static function (): void {
        });

        (new MigrationRunner())->run([$migration]);

        $this->assertSame(
            ['001_example'],
            get_option('association_manager_migrations')
        );
    }

    public function testCustomOptionKeyIsIsolatedFromTheDefault(): void
    {
        $coreRuns = 0;
        $implementationRuns = 0;

        $coreMigration = $this->fakeMigration('001_same_id', function () use (&$coreRuns): void {
            ++$coreRuns;
        });
        $implementationMigration = $this->fakeMigration('001_same_id', function () use (&$implementationRuns): void {
            ++$implementationRuns;
        });

        // Same migration id under two different option keys - if the two
        // runners shared bookkeeping, the second run() would see
        // "001_same_id" already recorded (from the first) and skip it.
        (new MigrationRunner())->run([$coreMigration]);
        (new MigrationRunner('ame_migrations'))->run([$implementationMigration]);

        $this->assertSame(1, $coreRuns);
        $this->assertSame(1, $implementationRuns, 'a colliding id under a different option key must still run - proves isolated bookkeeping');
    }

    public function testFailedMigrationIsNotRecordedAndRemainsRetryable(): void
    {
        $attempts = 0;
        $migration = $this->fakeMigration('001_flaky', function () use (&$attempts): void {
            ++$attempts;

            if ($attempts === 1) {
                throw new DatabaseWriteException('simulated write failure');
            }
        });

        $runner = new MigrationRunner('am_test_migrations');

        try {
            $runner->run([$migration]);
            $this->fail('Expected DatabaseWriteException was not thrown.');
        } catch (DatabaseWriteException) {
            // expected - the migration's own up() failed
        }

        $this->assertSame([], get_option('am_test_migrations', []), 'a failed migration must not be recorded as executed');

        // Retry: the same migration id is attempted again (up() succeeds
        // this time) and is now recorded.
        $runner->run([$migration]);

        $this->assertSame(2, $attempts, 'the failed migration must be retried, not skipped, on the next run()');
        $this->assertSame(['001_flaky'], get_option('am_test_migrations', []));
    }

    public function testMigrationSucceedsButTrackingPersistenceFailsIsNotRecordedAndRemainsRetryable(): void
    {
        $runCount = 0;
        $migration = $this->fakeMigration('001_example', function () use (&$runCount): void {
            ++$runCount;
        });

        $this->failNextUpdateOptionFor('am_test_migrations');

        $runner = new MigrationRunner('am_test_migrations');

        try {
            $runner->run([$migration]);
            $this->fail('Expected DatabaseWriteException was not thrown.');
        } catch (DatabaseWriteException) {
            // expected - up() succeeded but update_option() failed
        }

        $this->assertSame(1, $runCount, "the migration's up() did run - only the tracking write failed");
        $this->assertSame([], get_option('am_test_migrations', []), 'must not be reported as durably complete when the tracking option write failed');

        // Retry: update_option() succeeds this time.
        $runner->run([$migration]);

        $this->assertSame(2, $runCount, 'up() is called again on retry - it must be safe to call twice, which every migration in this codebase already is (create-if-missing or idempotent-by-id)');
        $this->assertSame(['001_example'], get_option('am_test_migrations', []));
    }

    private function fakeMigration(string $id, callable $up): MigrationInterface
    {
        return new class ($id, $up) implements MigrationInterface {
            public function __construct(
                private readonly string $id,
                private $up
            ) {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function up(): void
            {
                ($this->up)();
            }
        };
    }
}
