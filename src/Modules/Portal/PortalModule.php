<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal;

use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Portal\Public\PortalShortcode;
use AssociationManager\Modules\Portal\Services\PortalService;

defined( 'ABSPATH' ) || exit;

/**
 * Depends on Members/Documents/Certificates - registered last in
 * Kernel::registerModules() so all three have already register()'d
 * their services (same ADR-004 interface-dependency reasoning).
 */
final class PortalModule implements ModuleInterface {

    public function name(): string {
        return 'portal';
    }

    public function register( Container $container ): void {
        $container->set(
            PortalService::class,
            new PortalService(
                $container->get( MemberRepositoryInterface::class ),
                $container->get( DocumentService::class ),
                $container->get( CertificateService::class ),
            )
        );
    }

    public function boot( Container $container ): void {
        $service = $container->get( PortalService::class );

        ( new PortalShortcode( $service ) )->register();
    }
}
