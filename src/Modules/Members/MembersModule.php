<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Media\AttachmentStreamer;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Modules\Members\Admin\EditMemberPage;
use AssociationManager\Modules\Members\Admin\MemberBulkActions;
use AssociationManager\Modules\Members\Admin\MembersPage;
use AssociationManager\Modules\Members\Domain\MemberRegistrationException;
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
        $service              = $container->get( MemberService::class );
        $fieldRegistry        = $container->get( FieldRegistry::class );
        $fieldValueService    = $container->get( FieldValueService::class );
        $fieldValueRepository = $container->get( FieldValueRepositoryInterface::class );
        $attachmentStreamer   = $container->get( AttachmentStreamer::class );
        $expiryRunner         = $container->get( MembershipExpiryRunner::class );

        $statusRegistry = $container->get( MemberStatusRegistry::class );
        $planRegistry   = $container->get( MembershipPlanRegistry::class );
        $exporter       = new MemberCsvExporter( $service, $fieldRegistry, $fieldValueService );

        $adminMenu = $container->get( AdminMenu::class );
        $adminMenu->register( new MembersPage( $service, $statusRegistry, $planRegistry, $fieldRegistry, $fieldValueService ) );
        $adminMenu->register( new EditMemberPage( $service, $fieldRegistry, $fieldValueService, $fieldValueRepository ) );

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
            'admin_post_association_manager_save_member_identity',
            function () use ( $service ): void {
                $this->handleSaveMemberIdentity( $service );
            }
        );

        add_action(
            'admin_post_association_manager_send_password_reset',
            function () use ( $service ): void {
                $this->handleSendPasswordReset( $service );
            }
        );

        add_action(
            'admin_post_association_manager_create_portal_account',
            function () use ( $service ): void {
                $this->handleCreatePortalAccount( $service );
            }
        );

        add_action(
            'admin_post_association_manager_download_member_field_file',
            function () use ( $fieldValueRepository, $attachmentStreamer ): void {
                $this->handleDownloadMemberFieldFile( $fieldValueRepository, $attachmentStreamer );
            }
        );

        add_action(
            'admin_post_association_manager_approve_pending_field',
            function () use ( $fieldValueRepository ): void {
                $this->handleApprovePendingField( $fieldValueRepository );
            }
        );

        add_action(
            'admin_post_association_manager_reject_pending_field',
            function () use ( $fieldValueRepository ): void {
                $this->handleRejectPendingField( $fieldValueRepository );
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
            // The form has enctype="multipart/form-data" for exactly
            // this - saveWithUploads() also handles TYPE_FILE fields,
            // which arrive in $_FILES, not $_POST. bypassApproval: true
            // - the admin editing a member's fields directly is by
            // definition already the approver (see ADR-023 addendum).
            $fieldValueService->saveWithUploads( 'member', $memberId, $submitted, $_FILES['custom_fields'] ?? null, bypassApproval: true );
            $redirectArgs['am_notice'] = 'saved';
        } catch ( FieldValidationException ) {
            $redirectArgs['am_notice'] = 'invalid';
        }

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function handleSaveMemberIdentity( MemberService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;

        check_admin_referer( 'association_manager_save_member_identity_' . $memberId );

        $email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $firstName = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $lastName  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

        $service->updateIdentity( $memberId, $email, $firstName, $lastName );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => EditMemberPage::SLUG,
					'id'        => $memberId,
					'am_notice' => 'identity_saved',
				],
				admin_url( 'admin.php' )
            )
        );
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

    /**
     * Reset-link only, never a raw password field - the admin never sees
     * or sets a plaintext password. get_password_reset_key() is core
     * WordPress (wp-includes/user.php, always loaded) - deliberately not
     * requiring wp-login.php, whose bottom-of-file switch statement would
     * execute unrelated login-page-rendering logic if included outside
     * its normal entry-point context. The resulting URL is the exact
     * same "wp-login.php?action=rp" link WP's own core reset flow sends.
     */
    private function handleSendPasswordReset( MemberService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;

        check_admin_referer( 'association_manager_send_password_reset_' . $memberId );

        $redirectArgs = [
			'page' => EditMemberPage::SLUG,
			'id'   => $memberId,
		];

        $member = $service->find( $memberId );
        $user   = $member !== null && $member->wpUserId !== null ? get_userdata( $member->wpUserId ) : false;

        $redirectArgs['am_notice'] = $user !== false && $this->sendPasswordResetEmail( $user )
            ? 'reset_sent'
            : 'reset_failed';

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * For a member with no WP account yet - creates one with a random,
     * never-shown password (wp_generate_password()'s whole purpose here
     * is just to satisfy wp_insert_user()'s required parameter; the
     * member never learns it, they set their own via the reset email
     * sent immediately after), links it, then reuses the exact same
     * reset-email flow as handleSendPasswordReset() so a fresh account
     * and a reset go through one consistent path.
     */
    private function handleCreatePortalAccount( MemberService $service ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;

        check_admin_referer( 'association_manager_create_portal_account_' . $memberId );

        $redirectArgs = [
			'page' => EditMemberPage::SLUG,
			'id'   => $memberId,
		];

        $email = isset( $_POST['account_email'] ) ? sanitize_email( wp_unslash( $_POST['account_email'] ) ) : '';

        try {
            $member = $service->createPortalAccountFor( $memberId, $email );
        } catch ( MemberRegistrationException ) {
            $redirectArgs['am_notice'] = 'account_invalid';
            wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
            exit;
        }

        $user                      = $member->wpUserId !== null ? get_userdata( $member->wpUserId ) : false;
        $redirectArgs['am_notice'] = $user !== false && $this->sendPasswordResetEmail( $user )
            ? 'account_created'
            : 'account_created_email_failed';

        wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function sendPasswordResetEmail( \WP_User $user ): bool {
        $key = get_password_reset_key( $user );

        if ( is_wp_error( $key ) ) {
            return false;
        }

        $resetUrl = network_site_url(
            'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ),
            'login'
        );

        $message = sprintf(
            /* translators: %s: password reset URL */
            __( "Someone requested a password reset for your account.\n\nSet a new password here: %s\n\nIf you did not request this, you can ignore this email.", 'association-manager' ),
            $resetUrl
        );

        return wp_mail(
            $user->user_email,
            __( 'Set your password', 'association-manager' ),
            $message
        );
    }

    /**
     * Admin-only download for a member's file field - "which" selects
     * the live value or a not-yet-approved pending replacement (the
     * admin previewing what they're about to approve/reject). No
     * authorization beyond manage_options is needed here, unlike the
     * Portal's equivalent handler, which additionally has to confirm
     * the requesting WP user actually owns the member record - see
     * ADR-023 addendum for why that split lives in each Module rather
     * than in Core\Media\AttachmentStreamer itself.
     */
    private function handleDownloadMemberFieldFile( FieldValueRepositoryInterface $fieldValues, AttachmentStreamer $streamer ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_GET['member_id'] ) ? (int) $_GET['member_id'] : 0;
        $fieldKey = isset( $_GET['field_key'] ) ? sanitize_key( wp_unslash( $_GET['field_key'] ) ) : '';

        check_admin_referer( 'association_manager_download_member_field_file_' . $memberId . '_' . $fieldKey );

        $which        = ( $_GET['which'] ?? 'current' ) === 'pending' ? 'pending' : 'current';
        $attachmentId = $which === 'pending'
            ? ( $fieldValues->pendingFor( 'member', $memberId )[ $fieldKey ] ?? null )
            : $fieldValues->get( 'member', $memberId, $fieldKey );

        if ( $attachmentId === null || ! is_numeric( $attachmentId ) ) {
            wp_die( esc_html__( 'File not found.', 'association-manager' ), '', [ 'response' => 404 ] );
        }

        $streamer->stream( (int) $attachmentId );
    }

    private function handleApprovePendingField( FieldValueRepositoryInterface $fieldValues ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;
        $fieldKey = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';

        check_admin_referer( 'association_manager_approve_pending_field_' . $memberId . '_' . $fieldKey );

        $fieldValues->approvePending( 'member', $memberId, $fieldKey );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => EditMemberPage::SLUG,
					'id'        => $memberId,
					'am_notice' => 'pending_approved',
				],
				admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private function handleRejectPendingField( FieldValueRepositoryInterface $fieldValues ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $memberId = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0;
        $fieldKey = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';

        check_admin_referer( 'association_manager_reject_pending_field_' . $memberId . '_' . $fieldKey );

        $fieldValues->rejectPending( 'member', $memberId, $fieldKey );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => EditMemberPage::SLUG,
					'id'        => $memberId,
					'am_notice' => 'pending_rejected',
				],
				admin_url( 'admin.php' )
            )
        );
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
                        ( $data['email'] ?? '' ) !== '' ? sanitize_text_field( (string) $data['email'] ) : null,
                        ( $data['first_name'] ?? '' ) !== '' ? sanitize_text_field( (string) $data['first_name'] ) : null,
                        ( $data['last_name'] ?? '' ) !== '' ? sanitize_text_field( (string) $data['last_name'] ) : null
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
