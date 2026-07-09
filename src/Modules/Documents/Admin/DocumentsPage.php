<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Documents\Services\DocumentService;

defined( 'ABSPATH' ) || exit;

final class DocumentsPage implements AdminPageInterface {

    public const SLUG = 'association-manager-documents';

    public function __construct(
        private readonly DocumentService $service
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Documents', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Documents', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $visibilityOptions = [
            Visibility::VISIBILITY_PUBLIC  => __( 'Public', 'association-manager' ),
            Visibility::VISIBILITY_PRIVATE => __( 'Members only', 'association-manager' ),
            Visibility::VISIBILITY_ADMIN   => __( 'Admin only', 'association-manager' ),
        ];
        $noticeType        = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;
        $editId            = isset( $_GET['edit_id'] ) ? (int) $_GET['edit_id'] : null;

        if ( $editId !== null ) {
            $document = $this->service->find( $editId );

            require AM_PLUGIN_DIR . 'templates/admin/document-edit.php';

            return;
        }

        $documents = $this->service->all();

        require AM_PLUGIN_DIR . 'templates/admin/documents.php';
    }
}
