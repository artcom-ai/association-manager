<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\MigrationInterface;

// Pure seeding migration, same reasoning as migration 009 - no schema
// change, just default content for the two new event keys Documents
// and Certificates fire into the notification_templates table that
// migration 009 already created.
return new class() implements MigrationInterface {
    public function id(): string {
        return '014_seed_document_certificate_notification_templates';
    }

    public function up(): void {
        global $wpdb;

        $table = DatabaseManager::table( 'notification_templates' );

        $defaults = [
            [
                'event_key' => 'document_published',
                'subject'   => 'New document published: {title}',
                'body'      => "Hello,\n\nA new document has been published.\n\nTitle: {title}\nCategory: {category}",
            ],
            [
                'event_key' => 'certificate_issued',
                'subject'   => 'Your certificate is ready',
                'body'      => "Hello,\n\nA certificate has been issued to your membership (#{member_number}) on {issued_at}.\n\nThank you.",
            ],
        ];

        $now = current_time( 'mysql' );

        foreach ( $defaults as $default ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE event_key = %s AND channel = %s",
                    $default['event_key'],
                    'email'
                )
            );

            if ( $exists ) {
                continue;
            }

            $wpdb->insert(
                $table,
                [
                    'event_key'  => $default['event_key'],
                    'channel'    => 'email',
                    'subject'    => $default['subject'],
                    'body'       => $default['body'],
                    'updated_at' => $now,
                ],
                [ '%s', '%s', '%s', '%s', '%s' ]
            );
        }
    }
};
