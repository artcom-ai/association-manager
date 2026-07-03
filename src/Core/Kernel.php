<?php

declare(strict_types=1);

namespace AssociationManager\Core;

use AssociationManager\Core\Providers\CoreServiceProvider;

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
        $this->bootProviders();
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

    private function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }
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