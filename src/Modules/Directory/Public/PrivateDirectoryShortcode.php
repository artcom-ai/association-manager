<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory\Public;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Directory\Services\DirectoryService;

defined( 'ABSPATH' ) || exit;

final class PrivateDirectoryShortcode {

    public const TAG = 'association_manager_directory_private';

    private const PER_PAGE = 20;

    public function __construct(
        private readonly DirectoryService $service
    ) {
    }

    public function register(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
    }

    public function render(): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to view this directory.', 'association-manager' ) . '</p>';
        }

        $search = isset( $_GET['am_private_search'] ) ? sanitize_text_field( (string) $_GET['am_private_search'] ) : null;
        $page   = isset( $_GET['am_private_page'] ) ? (int) $_GET['am_private_page'] : 1;

        $result          = $this->service->paginatePrivate( new PaginationParams( $page, self::PER_PAGE ), $search );
        $customFields    = $this->service->visibleCustomFields( FieldDefinition::VISIBILITY_PRIVATE );
        $searchParamName = 'am_private_search';
        $pageParamName   = 'am_private_page';
        $currentSearch   = $search;

        ob_start();
        require AM_PLUGIN_DIR . 'templates/public/directory.php';

        return (string) ob_get_clean();
    }
}
