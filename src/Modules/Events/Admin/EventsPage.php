<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Events\Services\EventService;

defined( 'ABSPATH' ) || exit;

final class EventsPage implements AdminPageInterface {

    public function __construct(
        private readonly EventService $service
    ) {
    }

    public function slug(): string {
        return 'association-manager-events';
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Events', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Events', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $events = $this->service->all();

        require AM_PLUGIN_DIR . 'templates/admin/events.php';
    }
}
