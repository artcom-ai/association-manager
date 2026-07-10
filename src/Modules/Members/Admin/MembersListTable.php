<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Admin;

use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Services\MemberService;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class MembersListTable extends \WP_List_Table {

    private const PER_PAGE = 20;

    private const ENTITY_TYPE = 'member';

    /**
     * Populated by prepare_items() before display - one custom-field
     * lookup per visible row (at most PER_PAGE), not per column, since
     * FieldValueService::valuesFor() already returns every field's
     * value for a member in one call.
     *
     * @var array<int, array<string, string>>
     */
    private array $customFieldValues = [];

    public function __construct(
        private readonly MemberService $service,
        private readonly MemberStatusRegistry $statuses,
        private readonly MembershipPlanRegistry $plans,
        private readonly MemberBulkActions $bulkActions,
        private readonly FieldRegistry $fieldRegistry,
        private readonly FieldValueService $fieldValueService,
    ) {
        parent::__construct(
            [
				'singular' => 'member',
				'plural'   => 'members',
				'ajax'     => false,
			]
        );
    }

    /**
     * @return array<string, string>
     */
    public function get_columns(): array {
        $columns = [
            'cb'              => '<input type="checkbox" />',
            'name'            => __( 'Name', 'association-manager' ),
            'member_number'   => __( 'Member #', 'association-manager' ),
            'email'           => __( 'Email', 'association-manager' ),
            'status'          => __( 'Status', 'association-manager' ),
            'membership_type' => __( 'Membership type', 'association-manager' ),
            'expires_at'      => __( 'Expires', 'association-manager' ),
        ];

        foreach ( $this->fieldsShownInList() as $field ) {
            $columns[ 'field_' . $field->key ] = $field->label;
        }

        return $columns;
    }

    /**
     * @return \AssociationManager\Core\Fields\FieldDefinition[]
     */
    private function fieldsShownInList(): array {
        return array_values(
            array_filter(
                $this->fieldRegistry->forEntityType( self::ENTITY_TYPE ),
                static fn ( $field ): bool => $field->showInList
            )
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function get_sortable_columns(): array {
        return [
            'member_number' => [ 'member_number', false ],
            'expires_at'    => [ 'expires_at', false ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function get_bulk_actions(): array {
        return [
            'activate' => __( 'Activate', 'association-manager' ),
            'suspend'  => __( 'Suspend', 'association-manager' ),
            'archive'  => __( 'Archive', 'association-manager' ),
        ];
    }

    /**
     * @param Member $item
     */
    public function column_cb( $item ): string {
        return sprintf( '<input type="checkbox" name="member[]" value="%d" />', $item->id );
    }

    /**
     * @param Member $item
     */
    public function column_default( $item, $column_name ): string {
        if ( str_starts_with( $column_name, 'field_' ) ) {
            $fieldKey = substr( $column_name, strlen( 'field_' ) );
            $value    = $this->customFieldValues[ $item->requireId() ][ $fieldKey ] ?? null;

            return esc_html( $value !== null && $value !== '' ? $value : '—' );
        }

        return match ( $column_name ) {
            'name' => esc_html( $item->fullName() !== '' ? $item->fullName() : '—' ),
            'email' => esc_html( $item->email ?? '—' ),
            'status' => esc_html( $item->status ),
            'membership_type' => esc_html( $item->membershipType ?? '—' ),
            'expires_at' => esc_html( $item->expiresAt ?? '—' ),
            default => '',
        };
    }

    public function column_member_number( Member $item ): string {
        $editUrl = admin_url( 'admin.php?page=' . EditMemberPage::SLUG . '&id=' . $item->id );

        $actions = [
            'edit-fields' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $editUrl ),
                esc_html__( 'Edit fields', 'association-manager' )
            ),
            'quick-edit'  => sprintf(
                '<a href="#" class="am-quick-edit" data-id="%d" data-status="%s" data-membership-type="%s">%s</a>',
                $item->id,
                esc_attr( $item->status ),
                esc_attr( $item->membershipType ?? '' ),
                esc_html__( 'Quick Edit', 'association-manager' )
            ),
        ];

        return sprintf(
            '%s %s',
            esc_html( $item->memberNumber ?? '—' ),
            $this->row_actions( $actions )
        );
    }

    public function no_items(): void {
        esc_html_e( 'No members found.', 'association-manager' );
    }

    public function extra_tablenav( $which ): void {
        if ( $which !== 'top' ) {
            return;
        }

        $currentStatus = isset( $_GET['status'] ) ? sanitize_text_field( (string) $_GET['status'] ) : '';
        $currentPlan   = isset( $_GET['membership_type'] ) ? sanitize_text_field( (string) $_GET['membership_type'] ) : '';
        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php esc_html_e( 'All statuses', 'association-manager' ); ?></option>
                <?php foreach ( $this->statuses->all() as $status ) : ?>
                    <option value="<?php echo esc_attr( $status->key ); ?>" <?php selected( $currentStatus, $status->key ); ?>>
                        <?php echo esc_html( $status->label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="membership_type">
                <option value=""><?php esc_html_e( 'All plans', 'association-manager' ); ?></option>
                <?php foreach ( $this->plans->all() as $plan ) : ?>
                    <option value="<?php echo esc_attr( $plan->key ); ?>" <?php selected( $currentPlan, $plan->key ); ?>>
                        <?php echo esc_html( $plan->label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button( __( 'Filter', 'association-manager' ), '', 'filter_action', false ); ?>
        </div>
        <?php
    }

    public function prepare_items(): void {
        $this->maybeProcessBulkAction();

        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $criteria = new MemberSearchCriteria(
            status: $this->nonEmptyGetParam( 'status' ),
            membershipType: $this->nonEmptyGetParam( 'membership_type' ),
            search: $this->nonEmptyGetParam( 's' ),
        );

        $page   = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
        $params = new PaginationParams( $page, self::PER_PAGE );

        $result = $this->service->search( $criteria, $params );

        $this->items = $result->items;

        $this->customFieldValues = [];

        if ( $this->fieldsShownInList() !== [] ) {
            foreach ( $this->items as $member ) {
                $this->customFieldValues[ $member->requireId() ] = $this->fieldValueService->valuesFor( self::ENTITY_TYPE, $member->requireId() );
            }
        }

        $this->set_pagination_args(
            [
				'total_items' => $result->total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => $result->totalPages(),
			]
        );
    }

    private function maybeProcessBulkAction(): void {
        $action = $this->current_action();

        if ( $action === false || ! in_array( $action, [ 'activate', 'suspend', 'archive' ], true ) ) {
            return;
        }

        check_admin_referer( 'bulk-' . $this->_args['plural'] );

        $ids = array_map( 'intval', (array) ( $_REQUEST['member'] ?? [] ) );

        if ( $ids === [] ) {
            return;
        }

        $userId = get_current_user_id();

        $this->bulkActions->apply( $action, $ids, $userId > 0 ? $userId : null );
    }

    private function nonEmptyGetParam( string $key ): ?string {
        $value = isset( $_GET[ $key ] ) ? sanitize_text_field( (string) $_GET[ $key ] ) : '';

        return $value === '' ? null : $value;
    }
}
