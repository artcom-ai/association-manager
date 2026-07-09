<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepositoryInterface;
use AssociationManager\Modules\Certificates\Services\CertificateService;

defined( 'ABSPATH' ) || exit;

final class CertificatesPage implements AdminPageInterface {

    public const SLUG = 'association-manager-certificates';

    public function __construct(
        private readonly CertificateTemplateRepositoryInterface $templates,
        private readonly CertificateService $service,
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Certificates', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Certificates', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $templates    = $this->templates->all();
        $certificates = $this->service->all();
        $noticeType   = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;

        require AM_PLUGIN_DIR . 'templates/admin/certificates.php';
    }
}
