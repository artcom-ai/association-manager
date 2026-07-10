<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Core\Fields\Admin\FieldRenderer;
use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Modules\Members\Services\MemberService;

defined( 'ABSPATH' ) || exit;

/**
 * Registered as a submenu (for capability checking + routing) but
 * removed from the visible nav by MembersModule - reachable only via
 * the "Edit fields" link on the members list, never a nav item.
 */
final class EditMemberPage implements AdminPageInterface {

    public const SLUG = 'association-manager-member-edit';

    private const DOWNLOAD_ACTION = 'association_manager_download_member_field_file';

    public function __construct(
        private readonly MemberService $memberService,
        private readonly FieldRegistry $fieldRegistry,
        private readonly FieldValueService $fieldValueService,
        private readonly FieldValueRepositoryInterface $fieldValueRepository,
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Edit Member Fields', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Edit Member Fields', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $memberId = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
        $member   = $memberId > 0 ? $this->memberService->find( $memberId ) : null;

        if ( $member === null ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Member not found.', 'association-manager' ) . '</p></div>';

            return;
        }

        $fields     = $this->fieldRegistry->forEntityType( 'member' );
        $values     = $this->fieldValueService->valuesFor( 'member', $member->requireId() );
        $pending    = $this->fieldValueRepository->pendingFor( 'member', $member->requireId() );
        $renderer   = new FieldRenderer();
        $noticeType = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;

        $fileDownloadUrls    = [];
        $pendingDownloadUrls = [];

        foreach ( $fields as $field ) {
            if ( $field->type !== FieldDefinition::TYPE_FILE ) {
                continue;
            }

            if ( isset( $values[ $field->key ] ) ) {
                $fileDownloadUrls[ $field->key ] = $this->buildDownloadUrl( $member->requireId(), $field->key, 'current' );
            }

            if ( isset( $pending[ $field->key ] ) ) {
                $pendingDownloadUrls[ $field->key ] = $this->buildDownloadUrl( $member->requireId(), $field->key, 'pending' );
            }
        }

        require AM_PLUGIN_DIR . 'templates/admin/member-edit.php';
    }

    private function buildDownloadUrl( int $memberId, string $fieldKey, string $which ): string {
        $url = add_query_arg(
            [
				'action'    => self::DOWNLOAD_ACTION,
				'member_id' => $memberId,
				'field_key' => $fieldKey,
				'which'     => $which,
			],
            admin_url( 'admin-post.php' )
        );

        return wp_nonce_url( $url, self::DOWNLOAD_ACTION . '_' . $memberId . '_' . $fieldKey );
    }
}
