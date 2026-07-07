<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Rest;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Services\MemberService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class MembersController
{
    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly MemberService $service
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/members', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/members/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/members/(?P<id>\d+)/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activate'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/members/(?P<id>\d+)/suspend', [
            'methods' => 'POST',
            'callback' => [$this, 'suspend'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/members/(?P<id>\d+)/archive', [
            'methods' => 'POST',
            'callback' => [$this, 'archive'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/members/(?P<id>\d+)/renew', [
            'methods' => 'POST',
            'callback' => [$this, 'renew'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $params = PaginationParams::fromQuery($request->get_param('page'), $request->get_param('per_page'));

        $result = $this->service->paginate($params);

        $response = $result->toResponseArray();
        $response['data'] = array_map([$this, 'toArray'], $response['data']);

        return new WP_REST_Response($response, 200);
    }

    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $member = $this->service->find((int) $request->get_param('id'));

        if ($member === null) {
            return new WP_Error('am_member_not_found', 'Member not found.', ['status' => 404]);
        }

        return new WP_REST_Response($this->toArray($member), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $wpUserId = $request->get_param('wp_user_id');
        $membershipType = $request->get_param('membership_type');

        $member = $this->service->createMember(
            $wpUserId !== null ? (int) $wpUserId : null,
            $membershipType !== null ? sanitize_text_field((string) $membershipType) : null
        );

        return new WP_REST_Response($this->toArray($member), 201);
    }

    public function activate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, fn (int $id): Member => $this->service->activateMember($id, $this->currentUserId()));
    }

    public function suspend(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $reason = $request->get_param('reason');

        return $this->transition($request, fn (int $id): Member => $this->service->suspendMember(
            $id,
            $this->currentUserId(),
            $reason !== null ? sanitize_text_field((string) $reason) : null
        ));
    }

    public function archive(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, fn (int $id): Member => $this->service->archiveMember($id, $this->currentUserId()));
    }

    public function renew(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $expiresAt = (string) $request->get_param('expires_at');

        return $this->transition($request, fn (int $id): Member => $this->service->renewMembership(
            $id,
            $expiresAt,
            $this->currentUserId()
        ));
    }

    /**
     * @param callable(int): Member $action
     */
    private function transition(WP_REST_Request $request, callable $action): WP_REST_Response|WP_Error
    {
        try {
            $member = $action((int) $request->get_param('id'));
        } catch (\RuntimeException $e) {
            return new WP_Error('am_member_not_found', $e->getMessage(), ['status' => 404]);
        } catch (\LogicException $e) {
            return new WP_Error('am_member_invalid_transition', $e->getMessage(), ['status' => 409]);
        }

        return new WP_REST_Response($this->toArray($member), 200);
    }

    private function currentUserId(): ?int
    {
        $userId = get_current_user_id();

        return $userId > 0 ? $userId : null;
    }

    private function toArray(Member $member): array
    {
        return [
            'id' => $member->id,
            'uuid' => $member->uuid,
            'wp_user_id' => $member->wpUserId,
            'member_number' => $member->memberNumber,
            'status' => $member->status,
            'membership_type' => $member->membershipType,
            'joined_at' => $member->joinedAt,
            'expires_at' => $member->expiresAt,
            'approved_at' => $member->approvedAt,
        ];
    }
}
