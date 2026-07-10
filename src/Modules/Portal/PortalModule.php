<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal;

use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\MemberRegistrationException;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Notifications\Services\NotificationService;
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
            )
        );
    }

    public function boot( Container $container ): void {
        $service       = $container->get( PortalService::class );
        $memberService = $container->get( MemberService::class );

        ( new PortalShortcode( $service ) )->register();
        ( new RegistrationShortcode() )->register();

        // Registration is a public, unauthenticated action - both hooks
        // are required (admin_post_ for a logged-in visitor hitting the
        // form by mistake, admin_post_nopriv_ for the actual anonymous
        // case) or WordPress would 403 the request before it ever reaches
        // this handler.
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
            'admin_post_association_manager_submit_profile',
            function () use ( $service ): void {
                $this->handleSubmitProfile( $service );
            }
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
            $service->submitForApproval( $member, $submittedValues );
        } catch ( FieldValidationException $e ) {
            set_transient( 'am_profile_errors_' . get_current_user_id(), $e->errors(), 5 * MINUTE_IN_SECONDS );
        }

        $referer = wp_get_referer();
        wp_safe_redirect( $referer !== false ? $referer : home_url() );
        exit;
    }
}
