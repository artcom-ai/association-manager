<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Modules\Certificates\Admin\CertificateTemplatesPage;
use AssociationManager\Modules\Certificates\Admin\IssueCertificatePage;
use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepository;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepositoryInterface;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepository;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepositoryInterface;
use AssociationManager\Modules\Certificates\Rest\CertificatesController;
use AssociationManager\Modules\Certificates\Services\CertificateGenerator;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Depends on Members' MemberRepositoryInterface (to resolve a member id
 * to placeholders when issuing a certificate, and a WP user to their
 * own certificates in the REST controller) - same interface-dependency
 * pattern as ADR-004, so Members must register() before Certificates
 * (see Kernel::registerModules()).
 */
final class CertificatesModule implements ModuleInterface {

    public function name(): string {
        return 'certificates';
    }

    public function register( Container $container ): void {
        $container->set( CertificateTemplateRepositoryInterface::class, new CertificateTemplateRepository() );
        $container->set( CertificateRepositoryInterface::class, new CertificateRepository() );

        $container->set(
            CertificateGenerator::class,
            new CertificateGenerator( $container->get( TemplateRenderer::class ) )
        );

        $container->set(
            CertificateService::class,
            new CertificateService(
                $container->get( CertificateTemplateRepositoryInterface::class ),
                $container->get( CertificateRepositoryInterface::class ),
                $container->get( CertificateGenerator::class ),
            )
        );
    }

    public function boot( Container $container ): void {
        $templates = $container->get( CertificateTemplateRepositoryInterface::class );
        $service   = $container->get( CertificateService::class );
        $members   = $container->get( MemberRepositoryInterface::class );

        $adminMenu = $container->get( AdminMenu::class );
        $adminMenu->register( new CertificateTemplatesPage( $templates ) );
        $adminMenu->register( new IssueCertificatePage( $templates ) );

        add_action(
            'admin_post_association_manager_save_certificate_template',
            function () use ( $templates ): void {
                $this->handleSaveCertificateTemplate( $templates );
            }
        );

        add_action(
            'admin_post_association_manager_issue_certificate',
            function () use ( $service, $members ): void {
                $this->handleIssueCertificate( $service, $members );
            }
        );

        add_action(
            'rest_api_init',
            function () use ( $service, $members ): void {
				( new CertificatesController( $service, $members ) )->registerRoutes();
			}
        );
    }

    private function handleSaveCertificateTemplate( CertificateTemplateRepositoryInterface $templates ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $typeKey = isset( $_POST['type_key'] ) ? sanitize_text_field( (string) $_POST['type_key'] ) : '';

        check_admin_referer( 'association_manager_save_certificate_template_' . $typeKey );

        $name     = isset( $_POST['name'] ) ? sanitize_text_field( (string) $_POST['name'] ) : '';
        $htmlBody = isset( $_POST['html_body'] ) ? wp_kses_post( (string) $_POST['html_body'] ) : '';

        $existing = $templates->find( $typeKey );

        $template = $existing !== null
            ? $existing->withContent( $name, $htmlBody )
            : new CertificateTemplate( null, $typeKey, $name, $htmlBody );

        $templates->save( $template );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => CertificateTemplatesPage::SLUG,
					'type_key'  => $typeKey,
					'am_notice' => 'saved',
				],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private function handleIssueCertificate( CertificateService $service, MemberRepositoryInterface $members ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_issue_certificate' );

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;
        $typeKey  = isset( $_POST['type_key'] ) ? sanitize_text_field( (string) $_POST['type_key'] ) : '';

        $member = $members->find( $memberId );

        $redirectArgs = [ 'page' => IssueCertificatePage::SLUG ];

        if ( $member === null ) {
            $redirectArgs['am_notice'] = 'member_not_found';
        } else {
            $userId       = get_current_user_id();
            $placeholders = [
                'member_number'   => $member->memberNumber ?? '',
                'membership_type' => $member->membershipType ?? '',
                'issued_at'       => current_time( 'mysql' ),
            ];

            try {
                $service->issue( $member->requireId(), $typeKey, $placeholders, $userId > 0 ? $userId : null );
                $redirectArgs['am_notice'] = 'issued';
            } catch ( \RuntimeException ) {
                $redirectArgs['am_notice'] = 'issue_failed';
            }
        }

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }
}
