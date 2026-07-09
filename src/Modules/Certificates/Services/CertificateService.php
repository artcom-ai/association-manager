<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Services;

use AssociationManager\Modules\Certificates\Domain\Certificate;
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
     * @return Certificate[]
     */
    public function allForMember( int $memberId ): array {
        return $this->certificates->allForMember( $memberId );
    }

    public function find( int $id ): ?Certificate {
        return $this->certificates->find( $id );
    }

    /**
     * Issuing a certificate is a deliberate admin action (unlike
     * Notifications' silent no-op on a missing template) - a missing
     * template here is a real configuration error worth surfacing.
     *
     * @param array<string, string> $placeholders key => value, without braces
     */
    public function issue( int $memberId, string $typeKey, array $placeholders, ?int $issuedBy ): Certificate {
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

        $issuedAt = current_time( 'mysql' );

        $id = $this->certificates->insert(
            new Certificate(
                id: null,
                memberId: $memberId,
                typeKey: $typeKey,
                wpAttachmentId: $attachmentId,
                issuedAt: $issuedAt,
                issuedBy: $issuedBy,
            )
        );

        $certificate = $this->certificates->find( $id );

        if ( $certificate === null ) {
            throw new \RuntimeException( 'Certificate was not persisted.' );
        }

        do_action( 'association_manager_certificate_issued', $certificate );

        return $certificate;
    }
}
