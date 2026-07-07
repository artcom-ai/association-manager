<?php

declare(strict_types=1);

namespace AssociationManager\Core;

use AssociationManager\Core\Providers\CoreServiceProvider;
use AssociationManager\Modules\Directory\DirectoryModule;
use AssociationManager\Modules\Members\MembersModule;

defined('ABSPATH') || exit;

final class Kernel
{
    private Container $container;

    /**
     * @var ServiceProviderInterface[]
     */
    private array $providers = [];

    public function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): void
    {
        $this->registerProviders();
        $this->registerServices();
        $this->registerModules();
        $this->bootProviders();
        $this->bootModules();
        $this->registerHooks();
    }

    private function registerProviders(): void
    {
        $this->providers = [
            new CoreServiceProvider(),
        ];
    }

    private function registerServices(): void
    {
        foreach ($this->providers as $provider) {
            $provider->register($this->container);
        }
    }

    private function registerModules(): void
    {
        $moduleManager = $this->container->get(ModuleManager::class);

        $modules = [
            // Directory depends on Members' MemberRepositoryInterface, so
            // Members must register() first (see ADR-004).
            new MembersModule(),
            new DirectoryModule(),
        ];

        foreach ($modules as $module) {
            $moduleManager->register($module);
        }
    }

    private function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }
    }

    private function bootModules(): void
    {
        $this->container->get(ModuleManager::class)->boot($this->container);
    }

    private function registerHooks(): void
    {
        add_action('init', function (): void {
            do_action('association_manager_loaded');
        });
    }

    public function container(): Container
    {
        return $this->container;
    }
}