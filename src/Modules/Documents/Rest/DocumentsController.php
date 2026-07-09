<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Rest;

use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class DocumentsController {

    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly DocumentService $service,
        private readonly MemberRepositoryInterface $members,
    ) {
    }

    public function registerRoutes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/documents',
            [
				'methods'             => 'GET',
				'callback'            => [ $this, 'index' ],
				'permission_callback' => '__return_true',
			]
        );
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the REST route callback signature.
    public function index( WP_REST_Request $request ): WP_REST_Response {
        $member = is_user_logged_in() ? $this->members->findByWpUserId( get_current_user_id() ) : null;

        return new WP_REST_Response(
            array_map( [ $this, 'toArray' ], $this->service->listVisibleToViewer( $member ) ),
            200
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray( Document $document ): array {
        return [
            'id'          => $document->id,
            'title'       => $document->title,
            'description' => $document->description,
            'category'    => $document->category,
            'url'         => wp_get_attachment_url( $document->wpAttachmentId ),
        ];
    }
}
