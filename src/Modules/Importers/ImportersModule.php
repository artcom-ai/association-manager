<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Importers\Admin\ImportPage;
use AssociationManager\Modules\Importers\Cli\ImportCommand;
use AssociationManager\Modules\Importers\MemberPress\MemberPressUserSource;
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
 */
final class ImportersModule implements ModuleInterface {

    public function name(): string {
        return 'importers';
    }

    public function register( Container $container ): void {
        $container->set( FieldMappingRegistry::class, new FieldMappingRegistry() );
        $container->set( ImportSourceRegistry::class, new ImportSourceRegistry() );

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
    }

    public function boot( Container $container ): void {
        $sources       = $container->get( ImportSourceRegistry::class );
        $importService = $container->get( MemberImportService::class );

        $sources->register( new MemberPressUserSource() );

        $container->get( AdminMenu::class )->register( new ImportPage( $sources ) );

        add_action(
            'admin_post_association_manager_run_import',
            function () use ( $sources, $importService ): void {
                $this->handleRunImport( $sources, $importService );
            }
        );

        if ( class_exists( WP_CLI::class ) ) {
            WP_CLI::add_command( 'association-manager import', new ImportCommand( $sources, $importService ) );
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
}
