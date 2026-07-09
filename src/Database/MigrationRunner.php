<?php

declare(strict_types=1);

namespace AssociationManager\Database;

final class MigrationRunner {

    private const OPTION_KEY = 'association_manager_migrations';

    /**
     * @param MigrationInterface[] $migrations
     */
    public function run( array $migrations ): void {
        $executed = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $executed ) ) {
            $executed = [];
        }

        foreach ( $migrations as $migration ) {
            if ( in_array( $migration->id(), $executed, true ) ) {
                continue;
            }

            $migration->up();

            $executed[] = $migration->id();

            update_option( self::OPTION_KEY, $executed, false );
        }
    }
}
