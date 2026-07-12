<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal;

use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Media\AttachmentStreamer;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\MemberRegistrationException;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Notifications\Services\NotificationService;
use AssociationManager\Modules\Portal\Public\LoginShortcode;
use AssociationManager\Modules\Portal\Public\PortalShortcode;
use AssociationManager\Modules\Portal\Public\RegistrationShortcode;
use AssociationManager\Modules\Portal\Services\PortalService;

defined( 'ABSPATH' ) || exit;

/**
 * Depends on Members/Documents/Certificates/Notifications - registered
 * last in Kernel::registerModules() so all four have already register()'d
 * their services (same ADR-004 interface-dependency reasoning).
 */
final class PortalModule implements ModuleInterface {

    public function name(): string {
        return 'portal';
    }

    public function register( Container $container ): void {
        $container->set(
            PortalService::class,
            new PortalService(
                $container->get( MemberService::class ),
                $container->get( DocumentService::class ),
                $container->get( CertificateService::class ),
                $container->get( NotificationService::class ),
                $container->get( FieldRegistry::class ),
                $container->get( FieldValueService::class ),
                $container->get( FieldValueRepositoryInterface::class ),
            )
        );
    }

    public function boot( Container $container ): void {
        $service              = $container->get( PortalService::class );
        $memberService        = $container->get( MemberService::class );
        $fieldValueRepository = $container->get( FieldValueRepositoryInterface::class );
        $attachmentStreamer   = $container->get( AttachmentStreamer::class );

        ( new PortalShortcode( $service ) )->register();
        ( new RegistrationShortcode() )->register();
        ( new LoginShortcode() )->register();

        add_action( 'wp_enqueue_scripts', [ $this, 'maybeEnqueuePublicStyles' ] );

        // Registration/login are public, unauthenticated actions - both
        // hooks are required (admin_post_ for a logged-in visitor hitting
        // the form by mistake, admin_post_nopriv_ for the actual anonymous
        // case) or WordPress would 403 the request before it ever reaches
        // these handlers.
        add_action(
            'admin_post_association_manager_register',
            function () use ( $memberService ): void {
                $this->handleRegister( $memberService );
            }
        );
        add_action(
            'admin_post_nopriv_association_manager_register',
            function () use ( $memberService ): void {
                $this->handleRegister( $memberService );
            }
        );

        add_action(
            'admin_post_association_manager_login',
            function (): void {
                $this->handleLogin();
            }
        );
        add_action(
            'admin_post_nopriv_association_manager_login',
            function (): void {
                $this->handleLogin();
            }
        );

        add_action(
            'admin_post_association_manager_submit_profile',
            function () use ( $service ): void {
                $this->handleSubmitProfile( $service );
            }
        );

        add_action(
            'admin_post_association_manager_update_field_file',
            function () use ( $service ): void {
                $this->handleUpdateFieldFile( $service );
            }
        );

        add_action(
            'admin_post_association_manager_download_own_field_file',
            function () use ( $service, $fieldValueRepository, $attachmentStreamer ): void {
                $this->handleDownloadOwnFieldFile( $service, $fieldValueRepository, $attachmentStreamer );
            }
        );
    }

    /**
     * Conditional on shortcode presence, same pattern as
     * DirectoryModule::maybeEnqueueMapAssets() - login/register/portal
     * previously had no front-end stylesheet at all, so their buttons
     * (.button .button-primary) and tables (.form-table) rendered
     * unstyled outside wp-admin. This is presentation only - no markup
     * logic, validation, or workflow behavior changes with it.
     */
    public function maybeEnqueuePublicStyles(): void {
        if ( ! is_singular() ) {
            return;
        }

        $content = get_post()->post_content ?? '';

        $hasPortalShortcode = has_shortcode( $content, LoginShortcode::TAG )
            || has_shortcode( $content, RegistrationShortcode::TAG )
            || has_shortcode( $content, PortalShortcode::TAG );

        if ( ! $hasPortalShortcode ) {
            return;
        }

        wp_enqueue_style(
            'association-manager-public',
            AM_PLUGIN_URL . 'assets/css/public.css',
            [],
            AM_PLUGIN_VERSION
        );
    }

    private function handleRegister( MemberService $memberService ): void {
        check_admin_referer( 'association_manager_register' );

        $email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $password  = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
        $firstName = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $lastName  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
        $redirect  = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : home_url();

        try {
            $member = $memberService->registerNewMember( $email, $password, $firstName, $lastName );
        } catch ( MemberRegistrationException $e ) {
            $token = wp_generate_uuid4();

            set_transient(
                'am_register_error_' . $token,
                [
					'errors'     => $e->errors(),
					'email'      => $email,
					'first_name' => $firstName,
					'last_name'  => $lastName,
				],
                5 * MINUTE_IN_SECONDS
            );

            $referer = wp_get_referer();
            wp_safe_redirect( add_query_arg( [ 'am_register_error' => $token ], $referer !== false ? $referer : home_url() ) );
            exit;
        }

        if ( $member->wpUserId === null ) {
            // registerNewMember() always sets wpUserId - this can only
            // mean a real defect upstream, not a normal runtime state.
            throw new \LogicException( 'Registered member has no wpUserId.' );
        }

        wp_set_current_user( $member->wpUserId );
        wp_set_auth_cookie( $member->wpUserId );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Plain wp_signon() - this doesn't reinvent or weaken WP's own
     * login/cookie handling, only wraps it in a form that lives on a
     * normal front-end page instead of wp-login.php. Same
     * transient-keyed-by-token error round-trip as handleRegister(),
     * since the visitor isn't logged in yet when a login attempt fails.
     */
    private function handleLogin(): void {
        check_admin_referer( 'association_manager_login' );

        $login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
        $password = isset( $_POST['pwd'] ) ? (string) $_POST['pwd'] : '';
        $redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : home_url();

        $user = wp_signon(
            [
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => isset( $_POST['remember'] ),
			],
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            $token = wp_generate_uuid4();

            set_transient(
                'am_login_error_' . $token,
                [
					'errors' => [ 'login' => [ __( 'Incorrect username/email or password.', 'association-manager' ) ] ],
					'log'    => $login,
				],
                5 * MINUTE_IN_SECONDS
            );

            $referer = wp_get_referer();
            wp_safe_redirect( add_query_arg( [ 'am_login_error' => $token ], $referer !== false ? $referer : home_url() ) );
            exit;
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    private function handleSubmitProfile( PortalService $service ): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'You must be logged in.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_submit_profile' );

        $member = $service->memberFor( get_current_user_id() );

        if ( $member === null ) {
            wp_die( esc_html__( 'Your account is not linked to a member record.', 'association-manager' ) );
        }

        $submittedValues = isset( $_POST['custom_fields'] ) && is_array( $_POST['custom_fields'] )
            ? wp_unslash( $_POST['custom_fields'] )
            : [];

        try {
            $service->submitForApproval( $member, $submittedValues, $_FILES['custom_fields'] ?? null );
        } catch ( FieldValidationException $e ) {
            set_transient( 'am_profile_errors_' . get_current_user_id(), $e->errors(), 5 * MINUTE_IN_SECONDS );
        }

        $referer = wp_get_referer();
        wp_safe_redirect( $referer !== false ? $referer : home_url() );
        exit;
    }

    /**
     * Available regardless of onboarding/active status - see
     * PortalService::editableFileFieldsFor()/updateFileFields() and the
     * ADR-023 addendum. Fires association_manager_field_pending_approval
     * once per field routed to pending, rather than from Core, keeping
     * Core free of any notification-system dependency (same reasoning
     * as everywhere else this session an event fires from a Module, not
     * from Core\Fields itself).
     */
    private function handleUpdateFieldFile( PortalService $service ): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'You must be logged in.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_update_field_file' );

        $member = $service->memberFor( get_current_user_id() );

        if ( $member === null ) {
            wp_die( esc_html__( 'Your account is not linked to a member record.', 'association-manager' ) );
        }

        try {
            $pendingFieldKeys = $service->updateFileFields( $member, $_FILES['custom_fields'] ?? null );

            foreach ( $pendingFieldKeys as $fieldKey ) {
                do_action( 'association_manager_field_pending_approval', $member, $fieldKey );
            }
        } catch ( FieldValidationException $e ) {
            set_transient( 'am_profile_errors_' . get_current_user_id(), $e->errors(), 5 * MINUTE_IN_SECONDS );
        }

        $referer = wp_get_referer();
        wp_safe_redirect( $referer !== false ? $referer : home_url() );
        exit;
    }

    /**
     * Member-facing counterpart to Members' admin-only download handler
     * - the ownership check (does this WP user's own member record hold
     * this field's value) is the whole reason this lives in Portal
     * rather than being one shared Core endpoint; see ADR-023 addendum.
     */
    private function handleDownloadOwnFieldFile( PortalService $service, FieldValueRepositoryInterface $fieldValues, AttachmentStreamer $streamer ): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'You must be logged in.', 'association-manager' ) );
        }

        $member = $service->memberFor( get_current_user_id() );

        if ( $member === null ) {
            wp_die( esc_html__( 'Your account is not linked to a member record.', 'association-manager' ) );
        }

        $fieldKey = isset( $_GET['field_key'] ) ? sanitize_key( wp_unslash( $_GET['field_key'] ) ) : '';

        check_admin_referer( 'association_manager_download_own_field_file_' . $fieldKey );

        $which        = ( $_GET['which'] ?? 'current' ) === 'pending' ? 'pending' : 'current';
        $attachmentId = $which === 'pending'
            ? ( $fieldValues->pendingFor( 'member', $member->requireId() )[ $fieldKey ] ?? null )
            : $fieldValues->get( 'member', $member->requireId(), $fieldKey );

        if ( $attachmentId === null || ! is_numeric( $attachmentId ) ) {
            wp_die( esc_html__( 'File not found.', 'association-manager' ), '', [ 'response' => 404 ] );
        }

        $streamer->stream( (int) $attachmentId );
    }
}
