<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory\Rest;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Directory\Services\DirectoryService;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class DirectoryController
{
    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly DirectoryService $service
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/directory', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $params = PaginationParams::fromQuery($request->get_param('page'), $request->get_param('per_page'));

        return new WP_REST_Response($this->service->paginate($params)->toResponseArray(), 200);
    }
}
