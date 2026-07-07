<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory\Public;

use AssociationManager\Modules\Directory\Services\DirectoryService;

defined('ABSPATH') || exit;

final class DirectoryMapShortcode
{
    public const TAG = 'association_manager_directory_map';

    public function __construct(
        private readonly DirectoryService $service
    ) {
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    public function render(): string
    {
        $points = $this->service->mapPoints();

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/directory-map.php';

        return (string) ob_get_clean();
    }
}
