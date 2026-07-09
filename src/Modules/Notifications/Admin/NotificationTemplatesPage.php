<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class NotificationTemplatesPage implements AdminPageInterface {

    public const SLUG = 'association-manager-notification-templates';

    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Notification Templates', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Notifications', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $eventKey   = isset( $_GET['event_key'] ) ? sanitize_text_field( (string) $_GET['event_key'] ) : null;
        $noticeType = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;

        if ( $eventKey !== null ) {
            $template = $this->templates->find( $eventKey, NotificationChannel::EMAIL );

            require AM_PLUGIN_DIR . 'templates/admin/notification-template-edit.php';

            return;
        }

        $templates = $this->templates->all();

        require AM_PLUGIN_DIR . 'templates/admin/notification-templates.php';
    }
}
