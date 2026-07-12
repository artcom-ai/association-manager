<?php

declare(strict_types=1);

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Database\MigrationInterface;

return new class() implements MigrationInterface {
    public function id(): string {
        return '009_create_notification_templates_table';
    }

    public function up(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = DatabaseManager::table( 'notification_templates' );
        $charset = DatabaseManager::charsetCollate();

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_key VARCHAR(100) NOT NULL,
                channel VARCHAR(20) NOT NULL DEFAULT 'email',
                subject VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY event_channel (event_key, channel)
            ) {$charset};
        ";

        dbDelta( $sql );

        $this->seedDefaults( $table );
    }

    /**
     * Ship with real, editable content rather than an empty table -
     * the point of DB-backed templates is that an admin customizes
     * them, not that they start from nothing.
     *
     * is_default = 1 marks these rows as "still exactly what this
     * migration inserted, nothing has written to them since" - the
     * only place in the codebase that ever sets it to 1.
     * NotificationTemplateRepository::save() always forces it back to
     * 0 on any write, so it's a one-way flag: once anyone (an
     * administrator via the admin UI, or an implementation plugin's own
     * seeder) saves over a row, it can never be considered "still the
     * untouched default" again. This is what lets an implementation
     * plugin (e.g. ELESYTH) tell "genuinely still Core's default,
     * safe to replace with our own default copy" apart from "someone
     * already customized this, leave it alone" without inspecting the
     * template text itself - see
     * docs/adr/024-elesyth-implementation-migrations.md's second and
     * third addenda. This column was added directly to this
     * already-numbered migration so a genuinely fresh install gets it
     * from the start; a database that already recorded this migration
     * id before is_default existed never re-runs it, so
     * migration 025_add_is_default_to_notification_templates_table.php
     * exists specifically to backfill that column onto such a
     * database - see that migration's own docblock.
     */
    private function seedDefaults( string $table ): void {
        global $wpdb;

        $defaults = [
            [
                'event_key' => 'member_activated',
                'subject'   => 'Your membership is now active',
                'body'      => "Hello,\n\nYour membership (#{member_number}, {membership_type}) is now active. Welcome!\n\nThank you.",
            ],
            [
                'event_key' => 'member_suspended',
                'subject'   => 'Your membership has been suspended',
                'body'      => "Hello,\n\nYour membership (#{member_number}) has been suspended. Please contact us if you have questions.\n\nThank you.",
            ],
            [
                'event_key' => 'member_archived',
                'subject'   => 'Your membership has been archived',
                'body'      => "Hello,\n\nYour membership (#{member_number}) has been archived.\n\nThank you.",
            ],
            [
                'event_key' => 'membership_renewed',
                'subject'   => 'Your membership has been renewed',
                'body'      => "Hello,\n\nYour membership (#{member_number}) has been renewed and is now valid until {expires_at}.\n\nThank you.",
            ],
            [
                'event_key' => 'admin_new_member',
                'subject'   => 'New member registration',
                'body'      => "A new member has registered.\n\nMember #: {member_number}\nMembership type: {membership_type}",
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

            $result = $wpdb->insert(
                $table,
                [
                    'event_key'  => $default['event_key'],
                    'channel'    => 'email',
                    'subject'    => $default['subject'],
                    'body'       => $default['body'],
                    'is_default' => 1,
                    'updated_at' => $now,
                ],
                [ '%s', '%s', '%s', '%s', '%d', '%s' ]
            );

            if ( $result === false ) {
                throw new DatabaseWriteException(
                    "Failed to seed default notification template '{$default['event_key']}': {$wpdb->last_error}"
                );
            }
        }
    }
};
