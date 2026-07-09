<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Services;

use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Domain\CertificateStatus;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepositoryInterface;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class CertificateService {

    public function __construct(
        private readonly CertificateTemplateRepositoryInterface $templates,
        private readonly CertificateRepositoryInterface $certificates,
        private readonly CertificateGenerator $generator,
    ) {
    }

    /**
     * Member-facing view: only certificates actually issued to them -
     * drafts (not yet released) and revoked certificates never appear
     * here. Used by the Portal and the member-scoped REST endpoint.
     *
     * @return Certificate[]
     */
    public function issuedForMember( int $memberId ): array {
        return array_values(
            array_filter(
                $this->certificates->allForMember( $memberId ),
                static fn ( Certificate $certificate ): bool => $certificate->status === CertificateStatus::ISSUED
            )
        );
    }

    /**
     * Admin-facing view: every certificate, every status.
     *
     * @return Certificate[]
     */
    public function all(): array {
        return $this->certificates->all();
    }

    public function find( int $id ): ?Certificate {
        return $this->certificates->find( $id );
    }

    /**
     * Generates the PDF and stores it as a WP attachment immediately -
     * a draft is "generated, not yet released", not "not yet built"
     * (see ADR-019's addendum for why). No notification fires here;
     * issue() is the actual release/issuance moment.
     *
     * @param array<string, string> $placeholders key => value, without braces
     */
    public function createDraft( int $memberId, string $typeKey, array $placeholders, ?int $createdBy ): Certificate {
        $template = $this->templates->find( $typeKey );

        if ( $template === null ) {
            throw new \RuntimeException( "No certificate template configured for type \"{$typeKey}\"." );
        }

        $pdfBytes = $this->generator->generate( $template, $placeholders );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmpFile = wp_tempnam( 'certificate.pdf' );
        file_put_contents( $tmpFile, $pdfBytes );

        $attachmentId = media_handle_sideload(
            [
				'name'     => "certificate-{$typeKey}-{$memberId}.pdf",
				'tmp_name' => $tmpFile,
			],
            0
        );

        if ( is_wp_error( $attachmentId ) ) {
            throw new \RuntimeException( $attachmentId->get_error_message() );
        }

        $id = $this->certificates->insert(
            Certificate::draft( $memberId, $typeKey, $attachmentId, current_time( 'mysql' ), $createdBy )
        );

        return $this->mustFind( $id );
    }

    /**
     * The admin "Issue" action - draft -> issued. Fires
     * association_manager_certificate_issued so Notifications can
     * alert the member.
     */
    public function issue( int $certificateId ): Certificate {
        $issued = $this->mustFind( $certificateId )->issue( current_time( 'mysql' ) );

        $this->certificates->update( $issued );

        do_action( 'association_manager_certificate_issued', $issued );

        return $issued;
    }

    /**
     * The admin "Revoke" action - issued -> revoked. The certificate
     * row and its file are retained (audit trail); it simply stops
     * appearing in issuedForMember().
     */
    public function revoke( int $certificateId ): Certificate {
        $revoked = $this->mustFind( $certificateId )->revoke();

        $this->certificates->update( $revoked );

        return $revoked;
    }

    private function mustFind( int $id ): Certificate {
        $certificate = $this->certificates->find( $id );

        if ( $certificate === null ) {
            throw new \RuntimeException( "Certificate not found: {$id}" );
        }

        return $certificate;
    }
}
