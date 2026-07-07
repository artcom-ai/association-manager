<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Rest;

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

        register_rest_route(self::NAMESPACE, '/members/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $members = array_map([$this, 'toArray'], $this->service->all());

        return new WP_REST_Response($members, 200);
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

        $member = $this->service->register(
            $wpUserId !== null ? (int) $wpUserId : null,
            $membershipType !== null ? sanitize_text_field((string) $membershipType) : null
        );

        return new WP_REST_Response($this->toArray($member), 201);
    }

    public function approve(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $member = $this->service->approve((int) $request->get_param('id'));
        } catch (\RuntimeException $e) {
            return new WP_Error('am_member_not_found', $e->getMessage(), ['status' => 404]);
        }

        return new WP_REST_Response($this->toArray($member), 200);
    }

    private function toArray(Member $member): array
    {
        return [
            'id' => $member->id,
            'wp_user_id' => $member->wpUserId,
            'member_number' => $member->memberNumber,
            'status' => $member->status,
            'membership_type' => $member->membershipType,
            'joined_at' => $member->joinedAt,
            'approved_at' => $member->approvedAt,
        ];
    }
}
