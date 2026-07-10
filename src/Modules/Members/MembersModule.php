<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Members\Admin\EditMemberPage;
use AssociationManager\Modules\Members\Admin\MemberBulkActions;
use AssociationManager\Modules\Members\Admin\MembersPage;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepositoryInterface;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepositoryInterface;
use AssociationManager\Modules\Members\Rest\MembersController;
use AssociationManager\Modules\Members\Services\MemberCsvExporter;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Members\Services\MembershipExpiryCalculator;
use AssociationManager\Modules\Members\Services\MembershipExpiryRunner;

defined( 'ABSPATH' ) || exit;

final class MembersModule implements ModuleInterface {

    private const EXPIRY_CRON_HOOK = 'association_manager_expire_memberships';

    public function name(): string {
        return 'members';
    }

    public function register( Container $container ): void {
        $container->set( MemberRepositoryInterface::class, new MemberRepository() );
        $container->set( MemberStatusHistoryRepositoryInterface::class, new MemberStatusHistoryRepository() );
        $container->set( MemberStatusRegistry::class, new MemberStatusRegistry() );
        $container->set( MembershipPlanRegistry::class, new MembershipPlanRegistry() );
        $container->set( MembershipRenewalRepositoryInterface::class, new MembershipRenewalRepository() );

        $container->set(
            MemberService::class,
            new MemberService(
                $container->get( MemberRepositoryInterface::class ),
                $container->get( MemberStatusHistoryRepositoryInterface::class ),
                $container->get( MemberStatusRegistry::class ),
                $container->get( MembershipPlanRegistry::class ),
                $container->get( MembershipRenewalRepositoryInterface::class ),
            )
        );

        $container->set(
            MembershipExpiryCalculator::class,
            new MembershipExpiryCalculator( $container->get( MembershipPlanRegistry::class ) )
        );

        $container->set(
            MembershipExpiryRunner::class,
            new MembershipExpiryRunner(
                $container->get( MemberRepositoryInterface::class ),
                $container->get( MemberService::class ),
                $container->get( MembershipExpiryCalculator::class ),
            )
        );
    }

    public function boot( Container $container ): void {
        $service           = $container->get( MemberService::class );
        $fieldRegistry     = $container->get( FieldRegistry::class );
        $fieldValueService = $container->get( FieldValueService::class );
        $expiryRunner      = $container->get( MembershipExpiryRunner::class );

        $statusRegistry = $container->get( MemberStatusRegistry::class );
        $planRegistry   = $container->get( MembershipPlanRegistry::class );
        $exporter       = new MemberCsvExporter( $service, $fieldRegistry, $fieldValueService );

        $adminMenu = $container->get( AdminMenu::class );
        $adminMenu->register( new MembersPage( $service, $statusRegistry, $planRegistry ) );
        $adminMenu->register( new EditMemberPage( $service, $fieldRegistry, $fieldValueService ) );

        // EditMemberPage is registered (for routing/capability checks) but
        // isn't a nav item - only reachable via the "Edit fields" link.
        // Deliberately hidden via CSS, not remove_submenu_page(): removing
        // the entry from $submenu before admin.php resolves its own
        // page-hook/capability lookup can break that resolution on some
        // WordPress versions (produces "Sorry, you are not allowed to
        // access this page." even for a manage_options user) - CSS keeps
        // WordPress's own menu/capability machinery completely untouched.
        add_action(
            'admin_head',
            static function (): void {
				echo '<style>#adminmenu a[href*="page=' . esc_attr( EditMemberPage::SLUG ) . '"] { display: none; }</style>';
			}
        );

        add_action(
            'admin_post_association_manager_save_member_fields',
            function () use ( $fieldValueService, $fieldRegistry ): void {
                $this->handleSaveMemberFields( $fieldValueService, $fieldRegistry );
            }
        );

        add_action(
            'admin_post_association_manager_link_wp_user',
            function () use ( $service ): void {
                $this->handleLinkWpUser( $service );
            }
        );

        add_action(
            self::EXPIRY_CRON_HOOK,
            static function () use ( $expiryRunner ): void {
				$expiryRunner->run();
			}
        );

        // Activation schedules this once, but an already-active install
        // picking up this code update would never get it scheduled
        // without deactivate/reactivate - so also check defensively on
        // every admin_init, same reasoning as the migration-timing fix.
        add_action(
            'admin_init',
            static function (): void {
				if ( ! wp_next_scheduled( self::EXPIRY_CRON_HOOK ) ) {
					wp_schedule_event( time(), 'daily', self::EXPIRY_CRON_HOOK );
				}
			}
        );

        add_action(
            'rest_api_init',
            function () use ( $service, $fieldValueService ): void {
				( new MembersController( $service, $fieldValueService ) )->registerRoutes();
			}
        );

        add_action(
            'admin_post_association_manager_export_members',
            function () use ( $exporter ): void {
				$this->handleExportMembers( $exporter );
			}
        );

        add_action(
            'admin_post_association_manager_import_members',
            function () use ( $service ): void {
				$this->handleImportMembers( $service );
			}
        );

        add_action(
            'wp_ajax_association_manager_quick_edit_member',
            function () use ( $service ): void {
				$this->handleQuickEditMember( $service );
			}
        );

        add_action(
            'admin_enqueue_scripts',
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the admin_enqueue_scripts hook signature.
            static function ( string $hookSuffix ): void {
				if ( ( $_GET['page'] ?? '' ) !== MembersPage::SLUG ) {
					return;
				}

				wp_enqueue_script(
                    'association-manager-members-quick-edit',
                    AM_PLUGIN_URL . 'assets/js/members-quick-edit.js',
                    [],
                    AM_PLUGIN_VERSION,
                    true
				);

				wp_localize_script(
                    'association-manager-members-quick-edit',
                    'associationManagerQuickEdit',
                    [
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'association_manager_quick_edit_member' ),
                    ]
				);
			}
        );
    }

    private function handleSaveMemberFields( FieldValueService $fieldValueService, FieldRegistry $fieldRegistry ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;

        check_admin_referer( 'association_manager_save_member_fields_' . $memberId );

        $submitted = $_POST['custom_fields'] ?? [];
        $submitted = is_array( $submitted ) ? array_map( 'sanitize_text_field', $submitted ) : [];

        // Unchecked checkboxes aren't submitted by browsers at all; make
        // that explicit as "0" for every registered checkbox field.
        foreach ( $fieldRegistry->forEntityType( 'member' ) as $field ) {
            if ( $field->type === FieldDefinition::TYPE_CHECKBOX && ! array_key_exists( $field->key, $submitted ) ) {
                $submitted[ $field->key ] = '0';
            }
        }

        $redirectArgs = [
			'page' => EditMemberPage::SLUG,
			'id'   => $memberId,
		];

        try {
            $fieldValueService->save( 'member', $memberId, $submitted );
            $redirectArgs['am_notice'] = 'saved';
        } catch ( FieldValidationException ) {
            $redirectArgs['am_notice'] = 'invalid';
        }

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handleLinkWpUser( MemberService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;

        check_admin_referer( 'association_manager_link_wp_user_' . $memberId );

        $wpUserId = isset( $_POST['wp_user_id'] ) ? (int) $_POST['wp_user_id'] : 0;

        $redirectArgs = [
			'page' => EditMemberPage::SLUG,
			'id'   => $memberId,
		];

        if ( $wpUserId <= 0 ) {
            $redirectArgs['am_notice'] = 'link_invalid';
        } else {
            try {
                $service->linkWpUser( $memberId, $wpUserId );
                $redirectArgs['am_notice'] = 'linked';
            } catch ( \LogicException ) {
                $redirectArgs['am_notice'] = 'link_conflict';
            }
        }

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handleExportMembers( MemberCsvExporter $exporter ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_export_members' );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=members-' . gmdate( 'Y-m-d' ) . '.csv' );

        $out = fopen( 'php://output', 'w' );

        if ( $out === false ) {
            wp_die( esc_html__( 'Could not open output stream.', 'association-manager' ) );
        }

        fputcsv( $out, $exporter->headers() );

        foreach ( $exporter->rows() as $row ) {
            fputcsv( $out, $row );
        }

        fclose( $out );
        exit;
    }

    private function handleImportMembers( MemberService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_import_members' );

        $created = 0;
        $updated = 0;
        $errors  = [];

        $tmpName = $_FILES['import_file']['tmp_name'] ?? null;
        $handle  = $tmpName !== null ? fopen( $tmpName, 'r' ) : false;

        if ( $handle !== false ) {
            $header = fgetcsv( $handle );

            if ( $header !== false ) {
                // A CSV header row is never genuinely null-valued in practice; coerce
                // defensively so it's a valid array_combine() key list below.
                $header = array_map( static fn ( $value ): string => (string) $value, $header );
            }

            $rowNumber = 1;

            // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- standard read-until-EOF idiom, already comparison-guarded.
            while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                ++$rowNumber;

                if ( $header === false ) {
                    continue;
                }

                $data         = array_combine( $header, array_pad( $row, count( $header ), null ) );
                $memberNumber = trim( (string) ( $data['member_number'] ?? '' ) );

                if ( $memberNumber === '' ) {
                    $errors[] = "Row {$rowNumber}: member_number is required.";
                    continue;
                }

                try {
                    $result = $service->importRow(
                        $memberNumber,
                        ( $data['status'] ?? '' ) !== '' ? sanitize_text_field( (string) $data['status'] ) : null,
                        ( $data['membership_type'] ?? '' ) !== '' ? sanitize_text_field( (string) $data['membership_type'] ) : null,
                        ( $data['joined_at'] ?? '' ) !== '' ? (string) $data['joined_at'] : null,
                        ( $data['expires_at'] ?? '' ) !== '' ? (string) $data['expires_at'] : null,
                        get_current_user_id() ?: null,
                        ( $data['email'] ?? '' ) !== '' ? sanitize_text_field( (string) $data['email'] ) : null
                    );

                    if ( $result['action'] === 'created' ) {
                        ++$created;
                    } else {
                        ++$updated;
                    }
                } catch ( \Throwable $e ) {
                    $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
                }
            }

            fclose( $handle );
        } else {
            $errors[] = 'Could not read the uploaded file.';
        }

        $userId = get_current_user_id();
        set_transient(
            'association_manager_import_result_' . $userId,
            [
				'created' => $created,
				'updated' => $updated,
				'errors'  => $errors,
			],
            60
        );

        wp_safe_redirect( admin_url( 'admin.php?page=' . MembersPage::SLUG ) );
        exit;
    }

    private function handleQuickEditMember( MemberService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'association-manager' ) ], 403 );
        }

        check_ajax_referer( 'association_manager_quick_edit_member' );

        $memberId       = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;
        $status         = isset( $_POST['status'] ) ? sanitize_text_field( (string) $_POST['status'] ) : null;
        $membershipType = isset( $_POST['membership_type'] ) ? sanitize_text_field( (string) $_POST['membership_type'] ) : null;

        $member = $service->find( $memberId );

        if ( $member === null ) {
            wp_send_json_error( [ 'message' => __( 'Member not found.', 'association-manager' ) ], 404 );
        }

        $userId    = get_current_user_id();
        $changedBy = $userId > 0 ? $userId : null;

        try {
            if ( $status !== null && $status !== $member->status ) {
                $member = $service->transitionStatus( $memberId, $status, $changedBy );
            }

            if ( $membershipType !== $member->membershipType ) {
                $member = $service->updateMembershipType( $memberId, $membershipType !== '' ? $membershipType : null );
            }
        } catch ( \LogicException $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ], 409 );
        }

        wp_send_json_success(
            [
				'id'              => $member->id,
				'status'          => $member->status,
				'membership_type' => $member->membershipType,
			]
        );
    }
}
