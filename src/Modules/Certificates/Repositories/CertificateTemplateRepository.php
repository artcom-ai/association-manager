<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Database\DatabaseWriteException;
use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;

defined( 'ABSPATH' ) || exit;

final class CertificateTemplateRepository implements CertificateTemplateRepositoryInterface {

    public function find( string $typeKey ): ?CertificateTemplate {
        global $wpdb;

        $table = DatabaseManager::table( 'certificate_templates' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE type_key = %s", $typeKey ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * @return CertificateTemplate[]
     */
    public function all(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'certificate_templates' );

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY type_key ASC", ARRAY_A );

        return array_map( fn ( array $row ): CertificateTemplate => $this->hydrate( $row ), $rows ?: [] );
    }

    public function save( CertificateTemplate $template ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'certificate_templates' );
        $now   = current_time( 'mysql' );

        $existing = $this->find( $template->typeKey );

        if ( $existing === null ) {
            // is_default is always persisted as 0 here, regardless of
            // $template->isDefault - save() is the "someone is
            // deliberately writing this" path (an administrator, or an
            // implementation plugin's seeder), never the "this is an
            // untouched default" path. Only a default-seeding
            // migration's own direct $wpdb->insert() (bypassing this
            // repository) is allowed to set is_default = 1 - see
            // database/migrations/012_create_certificate_templates_table.php.
            $result = $wpdb->insert(
                $table,
                [
                    'type_key'   => $template->typeKey,
                    'name'       => $template->name,
                    'html_body'  => $template->htmlBody,
                    'is_default' => 0,
                    'updated_at' => $now,
                ],
                [ '%s', '%s', '%s', '%d', '%s' ]
            );

            if ( $result === false ) {
                throw new DatabaseWriteException(
                    "Failed to insert certificate template '{$template->typeKey}': {$wpdb->last_error}"
                );
            }

            return;
        }

        // Same reasoning as the insert branch above: this write, by
        // definition, is someone deliberately saving over whatever was
        // there - including if it was still is_default = 1 (Core's
        // untouched default). It can never remain "default" after this.
        $result = $wpdb->update(
            $table,
            [
                'name'       => $template->name,
                'html_body'  => $template->htmlBody,
                'is_default' => 0,
                'updated_at' => $now,
            ],
            [ 'id' => $existing->id ],
            [ '%s', '%s', '%d', '%s' ],
            [ '%d' ]
        );

        if ( $result === false ) {
            throw new DatabaseWriteException(
                "Failed to update certificate template '{$template->typeKey}': {$wpdb->last_error}"
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): CertificateTemplate {
        return new CertificateTemplate(
            id: (int) $row['id'],
            typeKey: $row['type_key'],
            name: $row['name'],
            htmlBody: $row['html_body'],
            isDefault: (bool) ( $row['is_default'] ?? false ),
        );
    }
}
