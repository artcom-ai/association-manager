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

    /**
     * Capability check for this feature is two layers, neither of them
     * current_user_can(): being a "member" is business-domain state,
     * not a WP role/capability, so there's no capability string that
     * would mean the right thing here. Layer 1 - is_user_logged_in() -
     * rules out anonymous visitors. Layer 2 - memberFor() returning
     * non-null - rules out logged-in WP users who aren't linked to a
     * Member record (see ADR-020's "link WP account" admin control).
     * Only a request that passes both ever reaches member data.
     */
    public function render(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to access the member portal.', 'association-manager' ) . '</p>';
        }

        $member = $this->service->memberFor( get_current_user_id() );

        if ( $member === null ) {
            return '<p>' . esc_html__( 'Your account is not yet linked to a member record. Please contact the association.', 'association-manager' ) . '</p>';
        }

        $documents     = $this->service->visibleDocuments();
        $certificates  = $this->service->certificatesFor( $member );
        $notifications = $this->service->notificationsFor( $member );

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/portal.php';
        $output = (string) ob_get_clean();

        // Marked read after the data has already been fetched for this
        // render, so this exact view still shows accurate sent/read
        // status - only the *next* visit reflects everything as read.
        $this->service->markNotificationsReadFor( $member );

        return $output;
    }
}
