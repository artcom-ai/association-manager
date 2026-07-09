<?php

declare(strict_types=1);

namespace AssociationManager\Database;

final class MigrationLoader {

    /**
     * @return MigrationInterface[]
     */
    public function load( string $path ): array {
        $files = glob( rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '*.php' );

        if ( $files === false ) {
            return [];
        }

        sort( $files );

        $migrations = [];

        foreach ( $files as $file ) {
            $migration = require $file;

            if ( $migration instanceof MigrationInterface ) {
                $migrations[] = $migration;
            }
        }

        return $migrations;
    }
}
