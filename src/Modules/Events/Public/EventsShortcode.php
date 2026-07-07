<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Public;

use AssociationManager\Modules\Events\Services\EventService;

defined('ABSPATH') || exit;

final class EventsShortcode
{
    public const TAG = 'association_manager_events';

    public function __construct(
        private readonly EventService $service
    ) {
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    public function render(): string
    {
        $events = $this->service->upcomingPublished();

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/events.php';

        return (string) ob_get_clean();
    }
}
