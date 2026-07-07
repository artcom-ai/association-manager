<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Services\MemberService;

defined('ABSPATH') || exit;

final class MembersPage implements AdminPageInterface
{
    public const SLUG = 'association-manager-members';

    public function __construct(
        private readonly MemberService $service,
        private readonly MemberStatusRegistry $statuses,
        private readonly MembershipPlanRegistry $plans,
    ) {
    }

    public function slug(): string
    {
        return self::SLUG;
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
        $bulkActions = new MemberBulkActions($this->service);
        $table = new MembersListTable($this->service, $this->statuses, $this->plans, $bulkActions);
        $table->prepare_items();

        $userId = get_current_user_id();
        $importResultKey = 'association_manager_import_result_' . $userId;
        $importResult = get_transient($importResultKey);

        if ($importResult !== false) {
            delete_transient($importResultKey);
        }

        $statuses = $this->statuses->all();

        require AM_PLUGIN_DIR . 'templates/admin/members.php';
    }
}
