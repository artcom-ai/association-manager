<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Members\Admin\MembersPage;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Members\Rest\MembersController;
use AssociationManager\Modules\Members\Services\MemberService;

defined('ABSPATH') || exit;

final class MembersModule implements ModuleInterface
{
    public function name(): string
    {
        return 'members';
    }

    public function register(Container $container): void
    {
        $container->set(MemberRepositoryInterface::class, new MemberRepository());

        $container->set(
            MemberService::class,
            new MemberService($container->get(MemberRepositoryInterface::class))
        );
    }

    public function boot(Container $container): void
    {
        $service = $container->get(MemberService::class);

        $container->get(AdminMenu::class)->register(new MembersPage($service));

        add_action('rest_api_init', function () use ($service): void {
            (new MembersController($service))->registerRoutes();
        });
    }
}
