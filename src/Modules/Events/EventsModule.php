<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Events\Admin\EventsPage;
use AssociationManager\Modules\Events\Public\EventsShortcode;
use AssociationManager\Modules\Events\Repositories\EventRepository;
use AssociationManager\Modules\Events\Repositories\EventRepositoryInterface;
use AssociationManager\Modules\Events\Rest\EventsController;
use AssociationManager\Modules\Events\Services\EventService;

defined('ABSPATH') || exit;

final class EventsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'events';
    }

    public function register(Container $container): void
    {
        $container->set(EventRepositoryInterface::class, new EventRepository());

        $container->set(
            EventService::class,
            new EventService($container->get(EventRepositoryInterface::class))
        );
    }

    public function boot(Container $container): void
    {
        $service = $container->get(EventService::class);

        $container->get(AdminMenu::class)->register(new EventsPage($service));

        (new EventsShortcode($service))->register();

        add_action('rest_api_init', function () use ($service): void {
            (new EventsController($service))->registerRoutes();
        });
    }
}
