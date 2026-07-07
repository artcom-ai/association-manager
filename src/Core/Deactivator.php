<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined('ABSPATH') || exit;

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('association_manager_expire_memberships');

        flush_rewrite_rules();
    }
}