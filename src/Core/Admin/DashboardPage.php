<?php

declare(strict_types=1);

namespace AssociationManager\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class DashboardPage implements AdminPageInterface {

    public const SLUG = 'association-manager';

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): ?string {
        return null;
    }

    public function pageTitle(): string {
        return __( 'Association Manager', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Association Manager', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        require AM_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
