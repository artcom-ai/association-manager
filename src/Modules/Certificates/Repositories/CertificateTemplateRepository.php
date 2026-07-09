<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Repositories;

use AssociationManager\Database\DatabaseManager;
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
            $wpdb->insert(
                $table,
                [
                    'type_key'   => $template->typeKey,
                    'name'       => $template->name,
                    'html_body'  => $template->htmlBody,
                    'updated_at' => $now,
                ],
                [ '%s', '%s', '%s', '%s' ]
            );

            return;
        }

        $wpdb->update(
            $table,
            [
                'name'       => $template->name,
                'html_body'  => $template->htmlBody,
                'updated_at' => $now,
            ],
            [ 'id' => $existing->id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
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
        );
    }
}
