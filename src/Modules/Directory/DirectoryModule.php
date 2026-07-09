<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory;

use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Directory\Public\DirectoryMapShortcode;
use AssociationManager\Modules\Directory\Public\DirectoryShortcode;
use AssociationManager\Modules\Directory\Public\PrivateDirectoryShortcode;
use AssociationManager\Modules\Directory\Rest\DirectoryController;
use AssociationManager\Modules\Directory\Services\DirectoryService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class DirectoryModule implements ModuleInterface {

    private const LEAFLET_VERSION = '1.9.4';

    public function name(): string {
        return 'directory';
    }

    public function register( Container $container ): void {
        $container->set(
            DirectoryService::class,
            new DirectoryService(
                $container->get( MemberRepositoryInterface::class ),
                $container->get( FieldRegistry::class ),
                $container->get( FieldValueService::class ),
            )
        );
    }

    public function boot( Container $container ): void {
        $service = $container->get( DirectoryService::class );

        ( new DirectoryShortcode( $service ) )->register();
        ( new PrivateDirectoryShortcode( $service ) )->register();
        ( new DirectoryMapShortcode( $service ) )->register();

        add_action(
            'rest_api_init',
            function () use ( $service ): void {
				( new DirectoryController( $service ) )->registerRoutes();
			}
        );

        add_action( 'wp_enqueue_scripts', [ $this, 'maybeEnqueueMapAssets' ] );
    }

    public function maybeEnqueueMapAssets(): void {
        if ( ! is_singular() || ! has_shortcode( get_post()->post_content ?? '', DirectoryMapShortcode::TAG ) ) {
            return;
        }

        wp_enqueue_style(
            'association-manager-leaflet',
            'https://unpkg.com/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.css',
            [],
            self::LEAFLET_VERSION
        );

        wp_enqueue_script(
            'association-manager-leaflet',
            'https://unpkg.com/leaflet@' . self::LEAFLET_VERSION . '/dist/leaflet.js',
            [],
            self::LEAFLET_VERSION,
            true
        );

        wp_enqueue_script(
            'association-manager-directory-map',
            AM_PLUGIN_URL . 'assets/js/directory-map.js',
            [ 'association-manager-leaflet' ],
            AM_PLUGIN_VERSION,
            true
        );
    }
}
