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
                '008_add_email_to_members_table',
                '009_create_notification_templates_table',
                '010_create_notification_queue_table',
                '011_create_documents_table',
                '012_create_certificate_templates_table',
                '013_create_certificates_table',
                '014_seed_document_certificate_notification_templates',
                '015_add_status_to_certificates_table',
                '016_add_import_source_to_members_table',
                '017_seed_member_submitted_for_approval_template',
                '018_create_field_definitions_table',
                '019_create_field_mappings_table',
                '020_add_name_to_members_table',
                '021_add_show_in_list_to_field_definitions_table',
                '022_add_pending_value_to_field_values_table',
                '023_add_requires_approval_to_field_definitions_table',
                '024_seed_member_field_pending_approval_template',
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
