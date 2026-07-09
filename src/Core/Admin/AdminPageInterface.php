<?php

declare(strict_types=1);

namespace AssociationManager\Core\Admin;

defined( 'ABSPATH' ) || exit;

interface AdminPageInterface {

    public function slug(): string;

    /**
     * Slug of the parent menu, or null to register a top-level menu.
     */
    public function parentSlug(): ?string;

    public function pageTitle(): string;

    public function menuTitle(): string;

    public function capability(): string;

    public function render(): void;
}
