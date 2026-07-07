<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Members\Services\MemberService;

defined('ABSPATH') || exit;

final class MembersPage implements AdminPageInterface
{
    public function __construct(
        private readonly MemberService $service
    ) {
    }

    public function slug(): string
    {
        return 'association-manager-members';
    }

    public function parentSlug(): ?string
    {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string
    {
        return __('Members', 'association-manager');
    }

    public function menuTitle(): string
    {
        return __('Members', 'association-manager');
    }

    public function capability(): string
    {
        return 'manage_options';
    }

    public function render(): void
    {
        $members = $this->service->all();

        require AM_PLUGIN_DIR . 'templates/admin/members.php';
    }
}
