<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Public;

use AssociationManager\Core\Fields\Admin\FieldRenderer;
use AssociationManager\Modules\Portal\Services\PortalService;

defined( 'ABSPATH' ) || exit;

final class PortalShortcode {

    public const TAG = 'association_manager_portal';

    private const PROFILE_ERRORS_TRANSIENT_PREFIX = 'am_profile_errors_';

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

        $documents      = $this->service->visibleDocuments();
        $certificates   = $this->service->certificatesFor( $member );
        $notifications  = $this->service->notificationsFor( $member );
        $customFields   = $this->service->customFieldsFor( $member );
        $canEditProfile = $this->service->canEditProfile( $member );
        $fieldRenderer  = new FieldRenderer();

        $editableFileFields = array_map(
            function ( array $row ): array {
                $row['downloadUrl']        = $row['value'] !== null ? $this->buildDownloadUrl( $row['field']->key, 'current' ) : null;
                $row['pendingDownloadUrl'] = $row['pending'] !== null ? $this->buildDownloadUrl( $row['field']->key, 'pending' ) : null;

                return $row;
            },
            $this->service->editableFileFieldsFor( $member )
        );

        $profileErrors = [];
        $transientKey  = self::PROFILE_ERRORS_TRANSIENT_PREFIX . get_current_user_id();
        $stored        = get_transient( $transientKey );

        if ( is_array( $stored ) ) {
            $profileErrors = $stored;
            delete_transient( $transientKey );
        }

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/portal.php';
        $output = (string) ob_get_clean();

        // Marked read after the data has already been fetched for this
        // render, so this exact view still shows accurate sent/read
        // status - only the *next* visit reflects everything as read.
        $this->service->markNotificationsReadFor( $member );

        return $output;
    }

    private function buildDownloadUrl( string $fieldKey, string $which ): string {
        $url = add_query_arg(
            [
				'action'    => 'association_manager_download_own_field_file',
				'field_key' => $fieldKey,
				'which'     => $which,
			],
            admin_url( 'admin-post.php' )
        );

        return wp_nonce_url( $url, 'association_manager_download_own_field_file_' . $fieldKey );
    }
}
