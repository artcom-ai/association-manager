<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Certificates\Domain\Certificate;

defined( 'ABSPATH' ) || exit;

final class CertificateRepository implements CertificateRepositoryInterface {

    public function find( int $id ): ?Certificate {
        global $wpdb;

        $table = DatabaseManager::table( 'certificates' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * @return Certificate[]
     */
    public function allForMember( int $memberId ): array {
        global $wpdb;

        $table = DatabaseManager::table( 'certificates' );

        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE member_id = %d ORDER BY id DESC", $memberId ),
            ARRAY_A
        );

        return array_map( fn ( array $row ): Certificate => $this->hydrate( $row ), $rows ?: [] );
    }

    public function insert( Certificate $certificate ): int {
        global $wpdb;

        $table = DatabaseManager::table( 'certificates' );

        $wpdb->insert(
            $table,
            [
                'member_id'        => $certificate->memberId,
                'type_key'         => $certificate->typeKey,
                'wp_attachment_id' => $certificate->wpAttachmentId,
                'issued_at'        => $certificate->issuedAt,
                'issued_by'        => $certificate->issuedBy,
            ],
            [ '%d', '%s', '%d', '%s', '%d' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): Certificate {
        return new Certificate(
            id: (int) $row['id'],
            memberId: (int) $row['member_id'],
            typeKey: $row['type_key'],
            wpAttachmentId: (int) $row['wp_attachment_id'],
            issuedAt: $row['issued_at'],
            issuedBy: $row['issued_by'] !== null ? (int) $row['issued_by'] : null,
        );
    }
}
