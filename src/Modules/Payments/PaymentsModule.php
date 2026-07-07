<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Payments\Admin\PaymentsPage;
use AssociationManager\Modules\Payments\Repositories\PaymentRepository;
use AssociationManager\Modules\Payments\Repositories\PaymentRepositoryInterface;
use AssociationManager\Modules\Payments\Rest\PaymentsController;
use AssociationManager\Modules\Payments\Services\PaymentService;

defined('ABSPATH') || exit;

final class PaymentsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'payments';
    }

    public function register(Container $container): void
    {
        $container->set(PaymentRepositoryInterface::class, new PaymentRepository());

        $container->set(
            PaymentService::class,
            new PaymentService($container->get(PaymentRepositoryInterface::class))
        );
    }

    public function boot(Container $container): void
    {
        $service = $container->get(PaymentService::class);

        $container->get(AdminMenu::class)->register(new PaymentsPage($service));

        add_action('rest_api_init', function () use ($service): void {
            (new PaymentsController($service))->registerRoutes();
        });
    }
}
