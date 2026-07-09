<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class CertificateTemplatesPage implements AdminPageInterface {

    public const SLUG = 'association-manager-certificate-templates';

    public function __construct(
        private readonly CertificateTemplateRepositoryInterface $templates
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Certificate Templates', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Certificates', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $typeKey    = isset( $_GET['type_key'] ) ? sanitize_text_field( (string) $_GET['type_key'] ) : null;
        $noticeType = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;

        if ( $typeKey !== null ) {
            $template = $this->templates->find( $typeKey );

            require AM_PLUGIN_DIR . 'templates/admin/certificate-template-edit.php';

            return;
        }

        $templates = $this->templates->all();

        require AM_PLUGIN_DIR . 'templates/admin/certificate-templates.php';
    }
}
