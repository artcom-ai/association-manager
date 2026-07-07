<?php

declare(strict_types=1);

namespace AssociationManager\Core;

use AssociationManager\Database\Migrator;

final class Activator
{
    public static function activate(): void
    {
        Migrator::installPending();
    }
}