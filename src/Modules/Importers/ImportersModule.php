<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepositoryInterface;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Importers\Admin\ImportPage;
use AssociationManager\Modules\Importers\Cli\ImportCommand;
use AssociationManager\Modules\Importers\MemberPress\MemberPressUserSource;
use AssociationManager\Modules\Importers\Repositories\FieldMappingRepository;
use AssociationManager\Modules\Importers\Repositories\FieldMappingRepositoryInterface;
use AssociationManager\Modules\Importers\Services\FieldDiscoveryService;
use AssociationManager\Modules\Importers\Services\MemberImportService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Members\Services\MemberService;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Generic import infrastructure (FieldMappingRegistry, ImportSourceInterface/
 * Registry, MemberImportService) plus the one concrete source this MVP
 * ships: MemberPressUserSource. MemberPress-specific reading logic lives
 * in Modules\Importers\MemberPress\, not Core - it's still generic Module
 * code (any association using MemberPress could reuse it), not
 * implementation-specific. What *is* implementation-specific - which
 * source meta keys actually matter and what Association Manager field
 * they map to - is deliberately not registered here; an implementation
 * (e.g. ELESYTH) supplies that by fetching FieldMappingRegistry from the
 * container and calling register(), same extension-point pattern as
 * Core\Fields\FieldRegistry. Depends on Members' MemberRepositoryInterface/
 * MemberService, so Members must register() first (see Kernel::
 * registerModules()).
 *
 * FieldMappingRegistry itself is now also DB-backed (FieldMappingRepository,
 * migration 019) - same treatment ADR-023 gave Core\Fields\FieldRegistry -
 * so mappings created by FieldDiscoveryService's "recreate from MemberPress"
 * admin action persist past the current request.
 */
final class ImportersModule implements ModuleInterface {

    public function name(): string {
        return 'importers';
    }

    public function register( Container $container ): void {
        $container->set( FieldMappingRegistry::class, new FieldMappingRegistry() );
        $container->set( ImportSourceRegistry::class, new ImportSourceRegistry() );
        $container->set( FieldMappingRepositoryInterface::class, new FieldMappingRepository() );

        $container->set(
            MemberImportService::class,
            new MemberImportService(
                $container->get( FieldMappingRegistry::class ),
                $container->get( FieldRegistry::class ),
                $container->get( FieldValueService::class ),
                $container->get( MemberRepositoryInterface::class ),
                $container->get( MemberService::class ),
            )
        );

        $container->set(
            FieldDiscoveryService::class,
            new FieldDiscoveryService(
                $container->get( FieldDefinitionRepositoryInterface::class ),
                $container->get( FieldMappingRepositoryInterface::class ),
            )
        );
    }

    public function boot( Container $container ): void {
        $sources          = $container->get( ImportSourceRegistry::class );
        $importService    = $container->get( MemberImportService::class );
        $discoveryService = $container->get( FieldDiscoveryService::class );

        $sources->register( new MemberPressUserSource() );

        $container->get( AdminMenu::class )->register( new ImportPage( $sources ) );

        // Same reasoning as CoreServiceProvider's FieldRegistry loading -
        // deferred to `init` (priority 5) rather than run synchronously
        // during boot(), since the table may not exist yet on the very
        // first request after a fresh activation (before admin_init has
        // applied pending migrations).
        add_action(
            'init',
            function () use ( $container ): void {
				$this->loadFieldMappingsIntoRegistry( $container );
			},
            5
        );

        add_action(
            'admin_post_association_manager_run_import',
            function () use ( $sources, $importService ): void {
                $this->handleRunImport( $sources, $importService );
            }
        );

        add_action(
            'admin_post_association_manager_discover_fields',
            function () use ( $sources, $importService, $discoveryService ): void {
                $this->handleDiscoverFields( $sources, $importService, $discoveryService );
            }
        );

        if ( class_exists( WP_CLI::class ) ) {
            WP_CLI::add_command( 'association-manager import', new ImportCommand( $sources, $importService ) );
        }
    }

    private function loadFieldMappingsIntoRegistry( Container $container ): void {
        $registry = $container->get( FieldMappingRegistry::class );
        $mappings = $container->get( FieldMappingRepositoryInterface::class );

        foreach ( $mappings->allSourceSystems() as $sourceSystem ) {
            foreach ( $mappings->all( $sourceSystem ) as $mapping ) {
                $registry->register( $sourceSystem, $mapping );
            }
        }
    }

    private function handleRunImport( ImportSourceRegistry $sources, MemberImportService $importService ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_run_import' );

        $sourceKey = isset( $_POST['source'] ) ? sanitize_text_field( (string) $_POST['source'] ) : '';
        $mode      = isset( $_POST['mode'] ) ? sanitize_text_field( (string) $_POST['mode'] ) : 'dry_run';

        $redirectArgs = [ 'page' => ImportPage::SLUG ];

        if ( $sourceKey === '' ) {
            $redirectArgs['am_notice'] = 'no_source';
            wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
            exit;
        }

        $source = $sources->get( $sourceKey );

        if ( $source === null ) {
            $redirectArgs['am_notice'] = 'unknown_source';
            wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
            exit;
        }

        $commit  = $mode === 'commit';
        $summary = $importService->run( $source, $commit );

        ImportPage::storeReport( $summary );

        $redirectArgs['am_notice'] = $commit ? 'commit_done' : 'dry_run_done';
        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handleDiscoverFields(
        ImportSourceRegistry $sources,
        MemberImportService $importService,
        FieldDiscoveryService $discoveryService
    ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_discover_fields' );

        $sourceKey = isset( $_POST['source'] ) ? sanitize_text_field( (string) $_POST['source'] ) : '';
        $source    = $sources->get( $sourceKey );

        if ( $source === null ) {
            wp_safe_redirect(
                add_query_arg(
                    [
						'page'      => ImportPage::SLUG,
						'am_notice' => 'unknown_source',
					],
					admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Reuses the exact same dry-run + allUnmappedKeys() a normal
        // "Preview" click already computes - no separate source-scanning
        // logic to keep in sync with MemberImportService's own.
        $summary = $importService->run( $source, false );
        $created = $discoveryService->createFieldsFromUnmappedKeys( $source->key(), $summary->allUnmappedKeys() );

        ImportPage::storeReport( $summary );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'             => ImportPage::SLUG,
					'am_notice'        => 'fields_created',
					'am_created_count' => $created,
				],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
