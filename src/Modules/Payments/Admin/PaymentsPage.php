<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Payments\Services\PaymentService;

defined( 'ABSPATH' ) || exit;

final class PaymentsPage implements AdminPageInterface {

    public function __construct(
        private readonly PaymentService $service
    ) {
    }

    public function slug(): string {
        return 'association-manager-payments';
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Payments', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Payments', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $payments = $this->service->all();

        require AM_PLUGIN_DIR . 'templates/admin/payments.php';
    }
}
