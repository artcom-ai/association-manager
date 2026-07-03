<?php

namespace AssociationManager\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static ?self $instance = null;

    public static function init(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->boot();
        }

        return self::$instance;
    }

    private function boot(): void
    {
        add_action('plugins_loaded', [$this, 'loaded']);
    }

    public function loaded(): void
    {
        do_action('association_manager_loaded');
    }
}