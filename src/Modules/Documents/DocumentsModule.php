<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Documents\Admin\DocumentsPage;
use AssociationManager\Modules\Documents\Repositories\DocumentRepository;
use AssociationManager\Modules\Documents\Repositories\DocumentRepositoryInterface;
use AssociationManager\Modules\Documents\Rest\DocumentsController;
use AssociationManager\Modules\Documents\Services\DocumentService;

defined( 'ABSPATH' ) || exit;

final class DocumentsModule implements ModuleInterface {

    public function name(): string {
        return 'documents';
    }

    public function register( Container $container ): void {
        $container->set( DocumentRepositoryInterface::class, new DocumentRepository() );

        $container->set(
            DocumentService::class,
            new DocumentService( $container->get( DocumentRepositoryInterface::class ) )
        );
    }

    public function boot( Container $container ): void {
        $service = $container->get( DocumentService::class );

        $container->get( AdminMenu::class )->register( new DocumentsPage( $service ) );

        add_action(
            'admin_post_association_manager_upload_document',
            function () use ( $service ): void {
                $this->handleUploadDocument( $service );
            }
        );

        add_action(
            'admin_post_association_manager_delete_document',
            function () use ( $service ): void {
                $this->handleDeleteDocument( $service );
            }
        );

        add_action(
            'rest_api_init',
            function () use ( $service ): void {
				( new DocumentsController( $service ) )->registerRoutes();
			}
        );
    }

    private function handleUploadDocument( DocumentService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_upload_document' );

        $title       = isset( $_POST['title'] ) ? sanitize_text_field( (string) $_POST['title'] ) : '';
        $description = isset( $_POST['description'] ) ? sanitize_textarea_field( (string) $_POST['description'] ) : null;
        $category    = isset( $_POST['category'] ) && $_POST['category'] !== '' ? sanitize_text_field( (string) $_POST['category'] ) : null;
        $visibility  = isset( $_POST['visibility'] ) ? sanitize_text_field( (string) $_POST['visibility'] ) : Visibility::VISIBILITY_ADMIN;
        $userId      = get_current_user_id();
        $fileData    = $_FILES['am_document_file'] ?? null;

        $redirectArgs = [ 'page' => DocumentsPage::SLUG ];

        if ( $title === '' || $fileData === null ) {
            $redirectArgs['am_notice'] = 'upload_failed';
        } else {
            try {
                $service->upload( $title, $description, $category, $visibility, $fileData, $userId > 0 ? $userId : null );
                $redirectArgs['am_notice'] = 'uploaded';
            } catch ( \RuntimeException ) {
                $redirectArgs['am_notice'] = 'upload_failed';
            }
        }

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handleDeleteDocument( DocumentService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $documentId = isset( $_POST['document_id'] ) ? (int) $_POST['document_id'] : 0;

        check_admin_referer( 'association_manager_delete_document_' . $documentId );

        $service->delete( $documentId );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => DocumentsPage::SLUG,
					'am_notice' => 'deleted',
				],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
