<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Services;

use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Repositories\DocumentRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class DocumentService {

    public function __construct(
        private readonly DocumentRepositoryInterface $repository
    ) {
    }

    /**
     * @return Document[]
     */
    public function listVisibleTo( string $viewerLevel ): array {
        return array_values(
            array_filter(
                $this->repository->all(),
                static fn ( Document $document ): bool => $document->isVisibleTo( $viewerLevel )
            )
        );
    }

    /**
     * @return Document[]
     */
    public function all(): array {
        return $this->repository->all();
    }

    public function find( int $id ): ?Document {
        return $this->repository->find( $id );
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileData
     */
    public function upload(
        string $title,
        ?string $description,
        ?string $category,
        string $visibility,
        array $fileData,
        ?int $uploadedBy
    ): Document {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $fieldKey            = 'am_document_upload';
        $_FILES[ $fieldKey ] = $fileData;
        $attachmentId        = media_handle_upload( $fieldKey, 0 );

        if ( is_wp_error( $attachmentId ) ) {
            throw new \RuntimeException( $attachmentId->get_error_message() );
        }

        $id = $this->repository->insert(
            Document::draft( $title, $description, $category, $attachmentId, $visibility, $uploadedBy )
        );

        $document = $this->repository->find( $id );

        if ( $document === null ) {
            throw new \RuntimeException( 'Document was not persisted.' );
        }

        do_action( 'association_manager_document_published', $document );

        return $document;
    }

    public function delete( int $id ): void {
        $document = $this->repository->find( $id );

        if ( $document === null ) {
            return;
        }

        $this->repository->delete( $id );

        wp_delete_attachment( $document->wpAttachmentId, true );
    }
}
