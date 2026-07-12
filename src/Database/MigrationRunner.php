<?php

declare(strict_types=1);

namespace AssociationManager\Database;

/**
 * @see DatabaseWriteException for the failure-propagation contract this
 * class relies on: a migration is only ever recorded as executed if both
 * its own up() and the tracking-option persistence succeed.
 */
final class MigrationRunner {

    private const DEFAULT_OPTION_KEY = 'association_manager_migrations';

    /**
     * $optionKey defaults to Core's own tracking option, unchanged from
     * before this parameter existed - Core's own Migrator::installPending()
     * relies on that default and needed no changes. Passing a different
     * key is what lets an implementation plugin (e.g. ELESYTH) reuse this
     * same, already-proven runner for its own versioned migrations/seed
     * steps against its own option, without sharing bookkeeping with
     * Core's schema migrations or requiring any ELESYTH-specific code to
     * live in Core - see docs/adr/024-elesyth-implementation-migrations.md.
     */
    public function __construct(
        private readonly string $optionKey = self::DEFAULT_OPTION_KEY
    ) {
    }

    /**
     * @param MigrationInterface[] $migrations
     */
    public function run( array $migrations ): void {
        $executed = get_option( $this->optionKey, [] );

        if ( ! is_array( $executed ) ) {
            $executed = [];
        }

        foreach ( $migrations as $migration ) {
            if ( in_array( $migration->id(), $executed, true ) ) {
                continue;
            }

            // If up() throws, execution stops here - $executed never
            // gains this id and update_option() below never runs, so
            // the migration is retried (not recorded as complete) on
            // the next run(). See DatabaseWriteException's docblock.
            $migration->up();

            $executed[] = $migration->id();

            // update_option() returning false here can only mean the
            // write itself failed - not "value unchanged", the usual
            // other reason it returns false - because $executed always
            // just grew by one id, so the new value can never equal
            // whatever old value was stored. Treat it as a real
            // persistence failure: throwing keeps this migration out of
            // $executed's *persisted* state, so it's retried next run()
            // even though its up() already succeeded once (up() must
            // therefore be safe to call again - satisfied by every
            // migration in this codebase being create-if-missing or
            // idempotent-by-migration-id already).
            $persisted = update_option( $this->optionKey, $executed, false );

            if ( ! $persisted ) {
                throw new DatabaseWriteException(
                    "Failed to persist migration tracking option '{$this->optionKey}' after running migration '{$migration->id()}'."
                );
            }
        }
    }
}
