<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Public;

use AssociationManager\Modules\Portal\Services\PortalService;

defined( 'ABSPATH' ) || exit;

final class PortalShortcode {

    public const TAG = 'association_manager_portal';

    public function __construct(
        private readonly PortalService $service
    ) {
    }

    public function register(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
    }

    public function render(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to access the member portal.', 'association-manager' ) . '</p>';
        }

        $member = $this->service->memberFor( get_current_user_id() );

        if ( $member === null ) {
            return '<p>' . esc_html__( 'Your account is not yet linked to a member record. Please contact the association.', 'association-manager' ) . '</p>';
        }

        $documents    = $this->service->visibleDocuments();
        $certificates = $this->service->certificatesFor( $member );

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/portal.php';

        return (string) ob_get_clean();
    }
}
