<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Members\Admin\EditMemberPage;
use AssociationManager\Modules\Members\Admin\MembersPage;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepositoryInterface;
use AssociationManager\Modules\Members\Rest\MembersController;
use AssociationManager\Modules\Members\Services\MemberService;

defined('ABSPATH') || exit;

final class MembersModule implements ModuleInterface
{
    public function name(): string
    {
        return 'members';
    }

    public function register(Container $container): void
    {
        $container->set(MemberRepositoryInterface::class, new MemberRepository());
        $container->set(MemberStatusHistoryRepositoryInterface::class, new MemberStatusHistoryRepository());
        $container->set(MemberStatusRegistry::class, new MemberStatusRegistry());

        $container->set(
            MemberService::class,
            new MemberService(
                $container->get(MemberRepositoryInterface::class),
                $container->get(MemberStatusHistoryRepositoryInterface::class),
                $container->get(MemberStatusRegistry::class),
            )
        );
    }

    public function boot(Container $container): void
    {
        $service = $container->get(MemberService::class);
        $fieldRegistry = $container->get(FieldRegistry::class);
        $fieldValueService = $container->get(FieldValueService::class);

        $adminMenu = $container->get(AdminMenu::class);
        $adminMenu->register(new MembersPage($service));
        $adminMenu->register(new EditMemberPage($service, $fieldRegistry, $fieldValueService));

        // EditMemberPage is registered (for routing/capability checks) but
        // isn't a nav item - only reachable via the "Edit fields" link.
        add_action('admin_menu', static function (): void {
            remove_submenu_page(DashboardPage::SLUG, EditMemberPage::SLUG);
        }, 999);

        add_action(
            'admin_post_association_manager_save_member_fields',
            function () use ($fieldValueService, $fieldRegistry): void {
                $this->handleSaveMemberFields($fieldValueService, $fieldRegistry);
            }
        );

        add_action('rest_api_init', function () use ($service, $fieldValueService): void {
            (new MembersController($service, $fieldValueService))->registerRoutes();
        });
    }

    private function handleSaveMemberFields(FieldValueService $fieldValueService, FieldRegistry $fieldRegistry): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'association-manager'));
        }

        $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;

        check_admin_referer('association_manager_save_member_fields_' . $memberId);

        $submitted = $_POST['custom_fields'] ?? [];
        $submitted = is_array($submitted) ? array_map('sanitize_text_field', $submitted) : [];

        // Unchecked checkboxes aren't submitted by browsers at all; make
        // that explicit as "0" for every registered checkbox field.
        foreach ($fieldRegistry->forEntityType('member') as $field) {
            if ($field->type === FieldDefinition::TYPE_CHECKBOX && !array_key_exists($field->key, $submitted)) {
                $submitted[$field->key] = '0';
            }
        }

        $redirectArgs = ['page' => EditMemberPage::SLUG, 'id' => $memberId];

        try {
            $fieldValueService->save('member', $memberId, $submitted);
            $redirectArgs['am_notice'] = 'saved';
        } catch (FieldValidationException) {
            $redirectArgs['am_notice'] = 'invalid';
        }

        wp_safe_redirect(add_query_arg($redirectArgs, admin_url('admin.php')));
        exit;
    }
}
