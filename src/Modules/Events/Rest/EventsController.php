<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Rest;

use AssociationManager\Modules\Events\Domain\Event;
use AssociationManager\Modules\Events\Services\EventService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class EventsController
{
    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly EventService $service
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/events', [
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

        register_rest_route(self::NAMESPACE, '/events/upcoming', [
            'methods' => 'GET',
            'callback' => [$this, 'upcoming'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array_map([$this, 'toArray'], $this->service->all()), 200);
    }

    public function upcoming(WP_REST_Request $request): WP_REST_Response
    {
        $events = array_map(
            fn (Event $event): array => [
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'starts_at' => $event->startsAt,
                'ends_at' => $event->endsAt,
            ],
            $this->service->upcomingPublished()
        );

        return new WP_REST_Response($events, 200);
    }

    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $event = $this->service->find((int) $request->get_param('id'));

        if ($event === null) {
            return new WP_Error('am_event_not_found', 'Event not found.', ['status' => 404]);
        }

        return new WP_REST_Response($this->toArray($event), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $capacity = $request->get_param('capacity');

        $event = $this->service->create(
            sanitize_text_field((string) $request->get_param('title')),
            $request->get_param('description') !== null ? sanitize_text_field((string) $request->get_param('description')) : null,
            $request->get_param('location') !== null ? sanitize_text_field((string) $request->get_param('location')) : null,
            (string) $request->get_param('starts_at'),
            $request->get_param('ends_at') !== null ? (string) $request->get_param('ends_at') : null,
            $capacity !== null ? (int) $capacity : null
        );

        return new WP_REST_Response($this->toArray($event), 201);
    }

    public function publish(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, 'publish');
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, 'cancel');
    }

    private function transition(WP_REST_Request $request, string $action): WP_REST_Response|WP_Error
    {
        try {
            $event = $this->service->{$action}((int) $request->get_param('id'));
        } catch (\RuntimeException $e) {
            return new WP_Error('am_event_not_found', $e->getMessage(), ['status' => 404]);
        } catch (\LogicException $e) {
            return new WP_Error('am_event_invalid_transition', $e->getMessage(), ['status' => 409]);
        }

        return new WP_REST_Response($this->toArray($event), 200);
    }

    private function toArray(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'starts_at' => $event->startsAt,
            'ends_at' => $event->endsAt,
            'capacity' => $event->capacity,
            'status' => $event->status,
        ];
    }
}
