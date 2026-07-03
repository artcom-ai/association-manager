<?php

declare(strict_types=1);

namespace AssociationManager\Core;

use AssociationManager\Database\MigrationLoader;
use AssociationManager\Database\MigrationRunner;

final class Activator
{
    public static function activate(): void
    {
        $loader = new MigrationLoader();

        $migrations = $loader->load(AM_PLUGIN_DIR . 'database/migrations');

        $runner = new MigrationRunner();
        $runner->run($migrations);
    }
}