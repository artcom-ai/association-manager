<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory;

use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Directory\Public\DirectoryShortcode;
use AssociationManager\Modules\Directory\Rest\DirectoryController;
use AssociationManager\Modules\Directory\Services\DirectoryService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined('ABSPATH') || exit;

final class DirectoryModule implements ModuleInterface
{
    public function name(): string
    {
        return 'directory';
    }

    public function register(Container $container): void
    {
        $container->set(
            DirectoryService::class,
            new DirectoryService($container->get(MemberRepositoryInterface::class))
        );
    }

    public function boot(Container $container): void
    {
        $service = $container->get(DirectoryService::class);

        (new DirectoryShortcode($service))->register();

        add_action('rest_api_init', function () use ($service): void {
            (new DirectoryController($service))->registerRoutes();
        });
    }
}
