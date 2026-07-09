<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Rest;

use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Services\DocumentService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class DocumentsController {

    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly DocumentService $service
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
        $viewerLevel = is_user_logged_in() ? Visibility::VISIBILITY_PRIVATE : Visibility::VISIBILITY_PUBLIC;

        return new WP_REST_Response(
            array_map( [ $this, 'toArray' ], $this->service->listVisibleTo( $viewerLevel ) ),
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
