<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Public;

defined( 'ABSPATH' ) || exit;

/**
 * `[association_manager_register redirect="/my-account/"]` - the
 * "redirect" attribute names the page carrying the Portal shortcode, so a
 * newly-registered member lands directly on their own dashboard. Errors
 * are round-tripped through a short-lived transient keyed by a random
 * token in the redirect URL (not a session/cookie mechanism) since the
 * visitor isn't logged in yet when validation fails - same admin-post +
 * transient shape ImportPage already uses, just keyed by token instead of
 * user id.
 */
final class RegistrationShortcode {

    public const TAG = 'association_manager_register';

    public function register(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function render( $atts = [] ): string {
        if ( is_user_logged_in() ) {
            return '<p>' . esc_html__( 'You are already logged in.', 'association-manager' ) . '</p>';
        }

        $atts = shortcode_atts( [ 'redirect' => home_url() ], (array) $atts, self::TAG );

        $errors    = [];
        $email     = '';
        $firstName = '';
        $lastName  = '';

        $errorToken = isset( $_GET['am_register_error'] ) ? sanitize_text_field( (string) $_GET['am_register_error'] ) : null;

        if ( $errorToken !== null ) {
            $stored = get_transient( 'am_register_error_' . $errorToken );

            if ( is_array( $stored ) ) {
                $errors    = $stored['errors'] ?? [];
                $email     = $stored['email'] ?? '';
                $firstName = $stored['first_name'] ?? '';
                $lastName  = $stored['last_name'] ?? '';

                delete_transient( 'am_register_error_' . $errorToken );
            }
        }

        $redirect = $atts['redirect'];

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/register.php';

        return (string) ob_get_clean();
    }
}
