<?php

declare(strict_types=1);

namespace AssociationManager\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

    /**
     * @var AdminPageInterface[]
     */
    private array $pages = [];

    public function register( AdminPageInterface $page ): void {
        $this->pages[ $page->slug() ] = $page;
    }

    public function boot(): void {
        add_action( 'admin_menu', [ $this, 'buildMenu' ] );
    }

    public function buildMenu(): void {
        foreach ( $this->pages as $page ) {
            if ( $page->parentSlug() === null ) {
                add_menu_page(
                    $page->pageTitle(),
                    $page->menuTitle(),
                    $page->capability(),
                    $page->slug(),
                    [ $page, 'render' ]
                );

                continue;
            }

            add_submenu_page(
                $page->parentSlug(),
                $page->pageTitle(),
                $page->menuTitle(),
                $page->capability(),
                $page->slug(),
                [ $page, 'render' ]
            );
        }
    }
}
