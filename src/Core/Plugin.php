<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?Kernel $kernel = null;

    public static function boot(): Kernel
    {
        if (self::$kernel === null) {
            self::$kernel = new Kernel();
            self::$kernel->boot();
        }

        return self::$kernel;
    }
}