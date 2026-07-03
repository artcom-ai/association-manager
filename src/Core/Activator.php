<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined('ABSPATH') || exit;

final class Activator
{
    public static function activate(): void
    {
        flush_rewrite_rules();
    }
}