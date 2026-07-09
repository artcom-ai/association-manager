<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Services;

use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Repositories\DocumentRepositoryInterface;
use AssociationManager\Modules\Members\Domain\Member;

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
     * The actual access-control policy behind "members can view/download
     * documents allowed for them": a viewer sees private-tier documents
     * only if they resolve to a real, linked Member - not merely being
     * logged into WordPress. $member is null for an anonymous visitor
     * *or* a logged-in WP account with no linked Member record; both get
     * the same public-only view. Callers (e.g. DocumentsController)
     * resolve $member via MemberRepositoryInterface::findByWpUserId()
     * before calling this.
     *
     * @return Document[]
     */
    public function listVisibleToViewer( ?Member $member ): array {
        return $this->listVisibleTo( $member !== null ? Visibility::VISIBILITY_PRIVATE : Visibility::VISIBILITY_PUBLIC );
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

    /**
     * Corrects a document's title/description/category/visibility in
     * place - never touches the underlying file (wpAttachmentId is
     * preserved as-is). Replacing the file itself is a distinct,
     * not-yet-built capability (versioning), out of scope here.
     */
    public function updateMetadata(
        int $id,
        string $title,
        ?string $description,
        ?string $category,
        string $visibility
    ): ?Document {
        $document = $this->repository->find( $id );

        if ( $document === null ) {
            return null;
        }

        $updated = $document->withMetadata( $title, $description, $category, $visibility );

        $this->repository->update( $updated );

        return $updated;
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
