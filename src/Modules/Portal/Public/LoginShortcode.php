<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Public;

defined( 'ABSPATH' ) || exit;

/**
 * `[association_manager_login redirect="/my-account/"]` - a branded
 * front-end login form, since the only alternative a member otherwise
 * has is WordPress's own wp-admin-styled wp-login.php. Authentication
 * itself is plain wp_signon() - this shortcode doesn't reinvent or
 * weaken WP's own login/cookie handling, only wraps it in a form that
 * lives on a normal front-end page next to Register/Portal. Same
 * error-round-trip shape as RegistrationShortcode (a short-lived
 * transient keyed by a random token in the redirect URL, since the
 * visitor isn't logged in yet when a login attempt fails).
 */
final class LoginShortcode {

    public const TAG = 'association_manager_login';

    private const ERROR_TRANSIENT_PREFIX = 'am_login_error_';

    public function register(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function render( $atts = [] ): string {
        $atts = shortcode_atts( [ 'redirect' => home_url() ], (array) $atts, self::TAG );

        if ( is_user_logged_in() ) {
            return sprintf(
                '<p>%s <a href="%s">%s</a></p>',
                esc_html__( 'You are already logged in.', 'association-manager' ),
                esc_url( $atts['redirect'] ),
                esc_html__( 'Go to your account', 'association-manager' )
            );
        }

        $errors = [];
        $login  = '';

        $errorToken = isset( $_GET['am_login_error'] ) ? sanitize_text_field( (string) $_GET['am_login_error'] ) : null;

        if ( $errorToken !== null ) {
            $stored = get_transient( self::ERROR_TRANSIENT_PREFIX . $errorToken );

            if ( is_array( $stored ) ) {
                $errors = $stored['errors'] ?? [];
                $login  = $stored['log'] ?? '';

                delete_transient( self::ERROR_TRANSIENT_PREFIX . $errorToken );
            }
        }

        $redirect = $atts['redirect'];

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/login.php';

        return (string) ob_get_clean();
    }
}
