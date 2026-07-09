<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory\Rest;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Directory\Services\DirectoryService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class DirectoryController {

    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly DirectoryService $service
    ) {
    }

    public function registerRoutes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/directory',
            [
				'methods'             => 'GET',
				'callback'            => [ $this, 'index' ],
				'permission_callback' => '__return_true',
			]
        );

        register_rest_route(
            self::NAMESPACE,
            '/directory/private',
            [
				'methods'             => 'GET',
				'callback'            => [ $this, 'privateIndex' ],
				'permission_callback' => 'is_user_logged_in',
			]
        );

        register_rest_route(
            self::NAMESPACE,
            '/directory/map',
            [
				'methods'             => 'GET',
				'callback'            => [ $this, 'map' ],
				'permission_callback' => '__return_true',
			]
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        $params = PaginationParams::fromQuery( $request->get_param( 'page' ), $request->get_param( 'per_page' ) );
        $search = $this->searchParam( $request );

        return new WP_REST_Response( $this->service->paginate( $params, $search )->toResponseArray(), 200 );
    }

    public function privateIndex( WP_REST_Request $request ): WP_REST_Response {
        $params = PaginationParams::fromQuery( $request->get_param( 'page' ), $request->get_param( 'per_page' ) );
        $search = $this->searchParam( $request );

        return new WP_REST_Response( $this->service->paginatePrivate( $params, $search )->toResponseArray(), 200 );
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the REST route callback signature.
    public function map( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( $this->service->mapPoints(), 200 );
    }

    private function searchParam( WP_REST_Request $request ): ?string {
        $search = $request->get_param( 'search' );

        return $search !== null && $search !== '' ? sanitize_text_field( (string) $search ) : null;
    }
}
