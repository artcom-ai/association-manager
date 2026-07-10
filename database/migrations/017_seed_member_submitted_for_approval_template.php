<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Pure seeding migration, same reasoning as migrations 009/014 - no
// schema change, just default content for the new
// "member_submitted_for_approval" event key NotificationsModule now
// fires when a self-registered member finishes their profile.
return new class() implements MigrationInterface {
    public function id(): string {
        return '017_seed_member_submitted_for_approval_template';
    }

    public function up(): void {
        global $wpdb;

        $table = DatabaseManager::table( 'notification_templates' );

        $eventKey = 'member_submitted_for_approval';
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
                'subject'    => 'A member is awaiting approval',
                'body'       => "Hello,\n\nA member has submitted their profile and is waiting for approval.\n\nMember #: {member_number}\nMembership type: {membership_type}\n\nPlease review them in the Members admin screen.",
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }
};
