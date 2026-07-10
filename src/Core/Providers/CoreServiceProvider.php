<?php

declare(strict_types=1);

namespace AssociationManager\Core\Providers;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Core\Container;
use AssociationManager\Core\Fields\Admin\FieldDefinitionsPage;
use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepository;
use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepositoryInterface;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Repositories\FieldValueRepositoryInterface;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\ModuleManager;
use AssociationManager\Core\ServiceProviderInterface;
use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Core\Visibility;

defined( 'ABSPATH' ) || exit;

final class CoreServiceProvider implements ServiceProviderInterface {

    private const VALID_TYPES = [
        FieldDefinition::TYPE_TEXT,
        FieldDefinition::TYPE_TEXTAREA,
        FieldDefinition::TYPE_NUMBER,
        FieldDefinition::TYPE_DATE,
        FieldDefinition::TYPE_SELECT,
        FieldDefinition::TYPE_CHECKBOX,
        FieldDefinition::TYPE_FILE,
        FieldDefinition::TYPE_LOCATION,
    ];

    private const VALID_VISIBILITIES = [
        Visibility::VISIBILITY_PUBLIC,
        Visibility::VISIBILITY_PRIVATE,
        Visibility::VISIBILITY_ADMIN,
    ];

    public function register( Container $container ): void {
        $container->set(
            ModuleManager::class,
            new ModuleManager()
        );

        $container->set(
            AdminMenu::class,
            new AdminMenu()
        );

        $container->set( FieldRegistry::class, new FieldRegistry() );
        $container->set( FieldValueRepositoryInterface::class, new FieldValueRepository() );
        $container->set( FieldDefinitionRepositoryInterface::class, new FieldDefinitionRepository() );
        $container->set( TemplateRenderer::class, new TemplateRenderer() );

        $container->set(
            FieldValueService::class,
            new FieldValueService(
                $container->get( FieldRegistry::class ),
                $container->get( FieldValueRepositoryInterface::class ),
                new FieldValidator()
            )
        );
    }

    public function boot( Container $container ): void {
        $fieldDefinitions = $container->get( FieldDefinitionRepositoryInterface::class );
        $fieldValues      = $container->get( FieldValueRepositoryInterface::class );

        $adminMenu = $container->get( AdminMenu::class );
        $adminMenu->register( new DashboardPage() );
        $adminMenu->register( new FieldDefinitionsPage( $fieldDefinitions ) );
        $adminMenu->boot();

        // Deferred to `init` (priority 5, before Kernel::registerHooks()'s
        // default-priority association_manager_loaded) rather than run
        // synchronously here - this runs on every request from
        // plugins_loaded, before admin_init has had a chance to apply a
        // just-added migration on a fresh activation, so the table isn't
        // guaranteed to exist yet at this exact point in the call chain.
        add_action(
            'init',
            function () use ( $container ): void {
				$this->loadFieldDefinitionsIntoRegistry( $container );
			},
            5
        );

        add_action(
            'admin_post_association_manager_save_field_definition',
            function () use ( $fieldDefinitions ): void {
				$this->handleSaveFieldDefinition( $fieldDefinitions );
			}
        );

        add_action(
            'admin_post_association_manager_delete_field_definition',
            function () use ( $fieldDefinitions, $fieldValues ): void {
				$this->handleDeleteFieldDefinition( $fieldDefinitions, $fieldValues );
			}
        );

        add_action(
            'admin_enqueue_scripts',
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the admin_enqueue_scripts hook signature.
            static function ( string $hookSuffix ): void {
				$isFieldsPage = ( $_GET['page'] ?? '' ) === FieldDefinitionsPage::SLUG;
				$isEditView   = isset( $_GET['new'] ) || isset( $_GET['field_key'] );

				if ( ! $isFieldsPage || ! $isEditView ) {
					return;
				}

				wp_enqueue_script(
                    'association-manager-field-definition-editor',
                    AM_PLUGIN_URL . 'assets/js/field-definition-editor.js',
                    [],
                    AM_PLUGIN_VERSION,
                    true
				);
			}
        );
    }

    private function loadFieldDefinitionsIntoRegistry( Container $container ): void {
        $registry    = $container->get( FieldRegistry::class );
        $definitions = $container->get( FieldDefinitionRepositoryInterface::class );

        foreach ( $definitions->allEntityTypes() as $entityType ) {
            foreach ( $definitions->all( $entityType ) as $field ) {
                $registry->register( $entityType, $field );
            }
        }
    }

    private function handleSaveFieldDefinition( FieldDefinitionRepositoryInterface $fields ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        check_admin_referer( 'association_manager_save_field_definition' );

        $isNew = isset( $_POST['is_new'] ) && $_POST['is_new'] === '1';
        $key   = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';
        $label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
        $type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : FieldDefinition::TYPE_TEXT;

        $visibility = isset( $_POST['visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['visibility'] ) ) : FieldDefinition::VISIBILITY_ADMIN;

        if ( $key === '' || $label === '' || ! in_array( $type, self::VALID_TYPES, true ) || ! in_array( $visibility, self::VALID_VISIBILITIES, true ) ) {
            $redirectArgs = [
				'page'      => FieldDefinitionsPage::SLUG,
				'am_notice' => 'invalid',
			];
            $redirectArgs = $isNew ? array_merge( $redirectArgs, [ 'new' => 1 ] ) : array_merge( $redirectArgs, [ 'field_key' => $key ] );

            wp_safe_redirect( add_query_arg( $redirectArgs, admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( $isNew && $fields->find( FieldDefinitionsPage::ENTITY_TYPE, $key ) !== null ) {
            wp_safe_redirect(
                add_query_arg(
                    [
						'page'      => FieldDefinitionsPage::SLUG,
						'am_notice' => 'key_taken',
						'new'       => 1,
					],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        $field = new FieldDefinition(
            key: $key,
            label: $label,
            type: $type,
            required: isset( $_POST['required'] ),
            options: $this->parseOptions( isset( $_POST['options'] ) ? (string) wp_unslash( $_POST['options'] ) : '' ),
            minLength: $this->nullableInt( $_POST['min_length'] ?? null ),
            maxLength: $this->nullableInt( $_POST['max_length'] ?? null ),
            minValue: $this->nullableFloat( $_POST['min_value'] ?? null ),
            maxValue: $this->nullableFloat( $_POST['max_value'] ?? null ),
            helpText: isset( $_POST['help_text'] ) && $_POST['help_text'] !== '' ? sanitize_text_field( wp_unslash( $_POST['help_text'] ) ) : null,
            order: isset( $_POST['order'] ) ? (int) $_POST['order'] : 0,
            visibility: $visibility,
            showInList: isset( $_POST['show_in_list'] ),
        );

        $fields->save( FieldDefinitionsPage::ENTITY_TYPE, $field );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => FieldDefinitionsPage::SLUG,
					'am_notice' => 'saved',
				],
				admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private function handleDeleteFieldDefinition( FieldDefinitionRepositoryInterface $fields, FieldValueRepositoryInterface $values ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $key = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';

        check_admin_referer( 'association_manager_delete_field_definition_' . $key );

        $fields->delete( FieldDefinitionsPage::ENTITY_TYPE, $key );
        $values->deleteForField( FieldDefinitionsPage::ENTITY_TYPE, $key );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => FieldDefinitionsPage::SLUG,
					'am_notice' => 'deleted',
				],
				admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * "value|Label" per line -> array<string, string>, null if nothing
     * usable was submitted. Only meaningful for TYPE_SELECT, but harmless
     * to store for any other type (FieldRenderer/FieldValidator simply
     * never read it).
     *
     * @return array<string, string>|null
     */
    private function parseOptions( string $raw ): ?array {
        $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );

        if ( $lines === [] ) {
            return null;
        }

        $options = [];

        foreach ( $lines as $line ) {
            $parts = explode( '|', $line, 2 );
            $value = trim( $parts[0] );
            $label = trim( $parts[1] ?? $parts[0] );

            if ( $value === '' ) {
                continue;
            }

            $options[ $value ] = $label;
        }

        return $options === [] ? null : $options;
    }

    private function nullableInt( mixed $value ): ?int {
        return $value !== null && $value !== '' ? (int) $value : null;
    }

    private function nullableFloat( mixed $value ): ?float {
        return $value !== null && $value !== '' ? (float) $value : null;
    }
}
