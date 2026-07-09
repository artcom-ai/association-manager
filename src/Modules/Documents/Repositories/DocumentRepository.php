<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Repositories;

use AssociationManager\Database\DatabaseManager;
use AssociationManager\Modules\Documents\Domain\Document;

defined( 'ABSPATH' ) || exit;

final class DocumentRepository implements DocumentRepositoryInterface {

    public function find( int $id ): ?Document {
        global $wpdb;

        $table = DatabaseManager::table( 'documents' );

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? $this->hydrate( $row ) : null;
    }

    /**
     * @return Document[]
     */
    public function all(): array {
        global $wpdb;

        $table = DatabaseManager::table( 'documents' );

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

        return array_map( fn ( array $row ): Document => $this->hydrate( $row ), $rows ?: [] );
    }

    public function insert( Document $document ): int {
        global $wpdb;

        $table = DatabaseManager::table( 'documents' );
        $now   = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            [
                'title'            => $document->title,
                'description'      => $document->description,
                'category'         => $document->category,
                'wp_attachment_id' => $document->wpAttachmentId,
                'visibility'       => $document->visibility,
                'uploaded_by'      => $document->uploadedBy,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public function delete( int $id ): void {
        global $wpdb;

        $table = DatabaseManager::table( 'documents' );

        $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate( array $row ): Document {
        return new Document(
            id: (int) $row['id'],
            title: $row['title'],
            description: $row['description'],
            category: $row['category'],
            wpAttachmentId: (int) $row['wp_attachment_id'],
            visibility: $row['visibility'],
            uploadedBy: $row['uploaded_by'] !== null ? (int) $row['uploaded_by'] : null,
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }
}
