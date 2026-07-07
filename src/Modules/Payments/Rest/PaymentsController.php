<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Payments\Rest;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Payments\Domain\Payment;
use AssociationManager\Modules\Payments\Services\PaymentService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

final class PaymentsController
{
    private const NAMESPACE = 'association-manager/v1';

    public function __construct(
        private readonly PaymentService $service
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/payments', [
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

        register_rest_route(self::NAMESPACE, '/payments/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'show'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/payments/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/payments/(?P<id>\d+)/fail', [
            'methods' => 'POST',
            'callback' => [$this, 'fail'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/payments/(?P<id>\d+)/refund', [
            'methods' => 'POST',
            'callback' => [$this, 'refund'],
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
        $payment = $this->service->find((int) $request->get_param('id'));

        if ($payment === null) {
            return new WP_Error('am_payment_not_found', 'Payment not found.', ['status' => 404]);
        }

        return new WP_REST_Response($this->toArray($payment), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $payment = $this->service->record(
            (int) $request->get_param('member_id'),
            (int) $request->get_param('amount_cents'),
            sanitize_text_field((string) ($request->get_param('currency') ?? 'EUR')),
            $request->get_param('method') !== null ? sanitize_text_field((string) $request->get_param('method')) : null,
            $request->get_param('reference') !== null ? sanitize_text_field((string) $request->get_param('reference')) : null
        );

        return new WP_REST_Response($this->toArray($payment), 201);
    }

    public function complete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, 'complete');
    }

    public function fail(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, 'fail');
    }

    public function refund(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->transition($request, 'refund');
    }

    private function transition(WP_REST_Request $request, string $action): WP_REST_Response|WP_Error
    {
        try {
            $payment = $this->service->{$action}((int) $request->get_param('id'));
        } catch (\RuntimeException $e) {
            return new WP_Error('am_payment_not_found', $e->getMessage(), ['status' => 404]);
        } catch (\LogicException $e) {
            return new WP_Error('am_payment_invalid_transition', $e->getMessage(), ['status' => 409]);
        }

        return new WP_REST_Response($this->toArray($payment), 200);
    }

    private function toArray(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'member_id' => $payment->memberId,
            'amount_cents' => $payment->amountCents,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'paid_at' => $payment->paidAt,
        ];
    }
}
