<?php

declare(strict_types=1);

namespace AssociationManager\Core\Providers;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Admin\DashboardPage;
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

        $container->set(
            AdminMenu::class,
            new AdminMenu()
        );
    }

    public function boot(Container $container): void
    {
        $adminMenu = $container->get(AdminMenu::class);
        $adminMenu->register(new DashboardPage());
        $adminMenu->boot();
    }
}