<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Rest;

use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepositoryInterface;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class NotificationsController
{
    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly NotificationTemplateRepositoryInterface $templates
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/notification-templates', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/notification-templates/(?P<event_key>[\w-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(array_map([$this, 'toArray'], $this->templates->all()), 200);
    }

    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $template = $this->templates->find((string) $request->get_param('event_key'), NotificationChannel::EMAIL);

        if ($template === null) {
            return new WP_Error('am_template_not_found', 'Template not found.', ['status' => 404]);
        }

        return new WP_REST_Response($this->toArray($template), 200);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $eventKey = (string) $request->get_param('event_key');
        $subject = sanitize_text_field((string) $request->get_param('subject'));
        $body = (string) $request->get_param('body');

        $existing = $this->templates->find($eventKey, NotificationChannel::EMAIL);

        $template = $existing !== null
            ? $existing->withContent($subject, $body)
            : new NotificationTemplate(null, $eventKey, NotificationChannel::EMAIL, $subject, $body);

        $this->templates->save($template);

        return new WP_REST_Response(
            $this->toArray($this->templates->find($eventKey, NotificationChannel::EMAIL)),
            200
        );
    }

    private function toArray(NotificationTemplate $template): array
    {
        return [
            'event_key' => $template->eventKey,
            'channel' => $template->channel,
            'subject' => $template->subject,
            'body' => $template->body,
        ];
    }
}
