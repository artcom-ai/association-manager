<?php

declare(strict_types=1);

namespace AssociationManager\Core\Providers;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleManager;
use AssociationManager\Core\ServiceProviderInterface;
use AssociationManager\Core\Templating\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

final class CoreServiceProvider implements ServiceProviderInterface {

    public function register( Container $container ): void {
        $container->set(
            ModuleManager::class,
            new ModuleManager()
        );

        $container->set(
            AdminMenu::class,
            new AdminMenu()
        );

        $container->set( FieldRegistry::class, new FieldRegistry() );
        $container->set( FieldValueRepositoryInterface::class, new FieldValueRepository() );
        $container->set( TemplateRenderer::class, new TemplateRenderer() );

        $container->set(
            FieldValueService::class,
            new FieldValueService(
                $container->get( FieldRegistry::class ),
                $container->get( FieldValueRepositoryInterface::class ),
                new FieldValidator()
            )
        );
    }

    public function boot( Container $container ): void {
        $adminMenu = $container->get( AdminMenu::class );
        $adminMenu->register( new DashboardPage() );
        $adminMenu->boot();
    }
}
