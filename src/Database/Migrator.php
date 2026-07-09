<?php

declare(strict_types=1);

namespace AssociationManager\Database;

final class Migrator {

    public static function installPending(): void {
        $loader = new MigrationLoader();

        $migrations = $loader->load( AM_PLUGIN_DIR . 'database/migrations' );

        ( new MigrationRunner() )->run( $migrations );
    }
}
