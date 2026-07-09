<?php

declare(strict_types=1);

namespace AssociationManager\Core;

use AssociationManager\Core\Providers\CoreServiceProvider;
use AssociationManager\Database\Migrator;
use AssociationManager\Modules\Certificates\CertificatesModule;
use AssociationManager\Modules\Directory\DirectoryModule;
use AssociationManager\Modules\Documents\DocumentsModule;
use AssociationManager\Modules\Events\EventsModule;
use AssociationManager\Modules\Members\MembersModule;
use AssociationManager\Modules\Notifications\NotificationsModule;
use AssociationManager\Modules\Payments\PaymentsModule;
use AssociationManager\Modules\Portal\PortalModule;

defined( 'ABSPATH' ) || exit;

final class Kernel {

    private Container $container;

    /**
     * @var ServiceProviderInterface[]
     */
    private array $providers = [];

    public function __construct() {
        $this->container = new Container();
    }

    public function boot(): void {
        $this->registerProviders();
        $this->registerServices();
        $this->registerModules();
        $this->bootProviders();
        $this->bootModules();
        $this->registerHooks();
    }

    private function registerProviders(): void {
        $this->providers = [
            new CoreServiceProvider(),
        ];
    }

    private function registerServices(): void {
        foreach ( $this->providers as $provider ) {
            $provider->register( $this->container );
        }
    }

    private function registerModules(): void {
        $moduleManager = $this->container->get( ModuleManager::class );

        $modules = [
            // Directory and Certificates both depend on Members'
            // MemberRepositoryInterface, so Members must register() first
            // (see ADR-004).
            new MembersModule(),
            new DirectoryModule(),
            new PaymentsModule(),
            new EventsModule(),
            // Documents has no dependency on any other module.
            new DocumentsModule(),
            new CertificatesModule(),
            // Notifications only ever consumes Members' fired events (plain
            // WordPress hooks, not container-resolved services), so it has
            // no registration-order constraint relative to Members.
            new NotificationsModule(),
            // Portal's register() resolves Members/Documents/Certificates'
            // services directly from the container (not just at boot
            // time), so it must be registered strictly after all three.
            new PortalModule(),
        ];

        foreach ( $modules as $module ) {
            $moduleManager->register( $module );
        }
    }

    private function bootProviders(): void {
        foreach ( $this->providers as $provider ) {
            $provider->boot( $this->container );
        }
    }

    private function bootModules(): void {
        $this->container->get( ModuleManager::class )->boot( $this->container );
    }

    private function registerHooks(): void {
        add_action(
            'init',
            function (): void {
				do_action( 'association_manager_loaded' );
			}
        );

        // Activation only runs Migrator::installPending() once at
        // activation time; this catches pending migrations shipped in a
        // code-only update to an already-active install (see ADR-005).
        add_action( 'admin_init', [ Migrator::class, 'installPending' ] );
    }

    public function container(): Container {
        return $this->container;
    }
}
