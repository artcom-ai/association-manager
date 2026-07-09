<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Rest;

use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Member-scoped: always resolves to the certificates issued to the
 * logged-in WP user's own linked Member record, never an arbitrary
 * member id from the request.
 */
final class CertificatesController {

    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly CertificateService $service,
        private readonly MemberRepositoryInterface $members,
    ) {
    }

    public function registerRoutes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/certificates',
            [
				'methods'             => 'GET',
				'callback'            => [ $this, 'index' ],
				'permission_callback' => 'is_user_logged_in',
			]
        );
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the REST route callback signature.
    public function index( WP_REST_Request $request ): WP_REST_Response {
        $member = $this->members->findByWpUserId( get_current_user_id() );

        if ( $member === null ) {
            return new WP_REST_Response( [], 200 );
        }

        return new WP_REST_Response(
            array_map( [ $this, 'toArray' ], $this->service->issuedForMember( $member->requireId() ) ),
            200
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray( Certificate $certificate ): array {
        return [
            'id'        => $certificate->id,
            'type_key'  => $certificate->typeKey,
            'issued_at' => $certificate->issuedAt,
            'url'       => wp_get_attachment_url( $certificate->wpAttachmentId ),
        ];
    }
}
