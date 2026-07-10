<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * List-then-edit-one, same shape as CertificateTemplatesPage/
 * NotificationTemplatesPage. Fixed to the "member" entity type for this
 * pass - it's the only entity type anything in this codebase actually
 * uses (Directory/Portal/Importers all hardcode it); supporting others
 * through this same page later is a trivial parameterization, not a
 * redesign, so it wasn't built speculatively now.
 */
final class FieldDefinitionsPage implements AdminPageInterface {

    public const SLUG = 'association-manager-fields';

    public const ENTITY_TYPE = 'member';

    public function __construct(
        private readonly FieldDefinitionRepositoryInterface $fields
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Member Fields', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Member Fields', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function render(): void {
        $noticeType = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;
        $isNew      = isset( $_GET['new'] );
        $fieldKey   = isset( $_GET['field_key'] ) ? sanitize_text_field( (string) $_GET['field_key'] ) : null;

        if ( $isNew ) {
            $field = null;

            require AM_PLUGIN_DIR . 'templates/admin/field-definition-edit.php';

            return;
        }

        if ( $fieldKey !== null ) {
            $field = $this->fields->find( self::ENTITY_TYPE, $fieldKey );

            require AM_PLUGIN_DIR . 'templates/admin/field-definition-edit.php';

            return;
        }

        $fields = $this->fields->all( self::ENTITY_TYPE );

        require AM_PLUGIN_DIR . 'templates/admin/field-definitions.php';
    }
}
