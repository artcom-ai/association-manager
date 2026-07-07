<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Database;

use AssociationManager\Database\MigrationLoader;
use AssociationManager\Tests\Support\TestCase;

final class MigrationLoaderTest extends TestCase
{
    public function testLoadsEveryMigrationInIdOrder(): void
    {
        $loader = new MigrationLoader();

        $migrations = $loader->load(AM_PLUGIN_DIR . 'database/migrations');
        $ids = array_map(static fn ($migration): string => $migration->id(), $migrations);

        $this->assertSame(
            [
                '001_create_members_table',
                '002_create_payments_table',
                '003_create_events_table',
                '004_extend_members_table',
                '005_create_member_status_history_table',
                '006_create_field_values_table',
                '007_create_membership_renewals_table',
            ],
            $ids
        );
    }

    public function testReturnsEmptyArrayForANonExistentDirectory(): void
    {
        $loader = new MigrationLoader();

        $this->assertSame([], $loader->load(AM_PLUGIN_DIR . 'no/such/directory'));
    }
}
