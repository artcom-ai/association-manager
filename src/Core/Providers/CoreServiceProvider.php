<?php

declare(strict_types=1);

namespace AssociationManager\Core\Providers;

use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleManager;
use AssociationManager\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

final class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            ModuleManager::class,
            new ModuleManager()
        );
    }

    public function boot(Container $container): void
    {
        // Core services boot here.
    }
}