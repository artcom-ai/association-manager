<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class IssueCertificatePage implements AdminPageInterface {

    public const SLUG = 'association-manager-issue-certificate';

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
        return __( 'Issue Certificate', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Issue Certificate', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $templates  = $this->templates->all();
        $noticeType = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;

        require AM_PLUGIN_DIR . 'templates/admin/issue-certificate.php';
    }
}
