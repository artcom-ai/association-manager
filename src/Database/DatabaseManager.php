<?php

declare(strict_types=1);

namespace AssociationManager\Database;

final class DatabaseManager {

    public static function table( string $name ): string {
        global $wpdb;

        return $wpdb->prefix . 'am_' . $name;
    }

    public static function charsetCollate(): string {
        global $wpdb;

        return $wpdb->get_charset_collate();
    }
}
