<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Pure seeding migration, same reasoning/pattern as migration 017 - no
// schema change, just default content for the new
// "member_field_pending_approval" event key NotificationsModule now
// fires when a member submits a replacement for an approval-gated file
// field (see ADR-023 addendum).
return new class() implements MigrationInterface {
    public function id(): string {
        return '024_seed_member_field_pending_approval_template';
    }

    public function up(): void {
        global $wpdb;

        $table = DatabaseManager::table( 'notification_templates' );

        $eventKey = 'member_field_pending_approval';
        $channel  = 'email';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE event_key = %s AND channel = %s",
                $eventKey,
                $channel
            )
        );

        if ( $exists ) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'event_key'  => $eventKey,
                'channel'    => $channel,
                'subject'    => 'A member has submitted a file awaiting approval',
                'body'       => "Hello,\n\nA member has uploaded a replacement file for a field that requires approval.\n\nMember #: {member_number}\nField: {field_label}\n\nPlease review it in the Members admin screen.",
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }
};
