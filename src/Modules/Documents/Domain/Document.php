<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Domain;

use AssociationManager\Core\Visibility;

defined( 'ABSPATH' ) || exit;

final class Document {

    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $category,
        public readonly int $wpAttachmentId,
        public readonly string $visibility,
        public readonly ?int $uploadedBy,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }

    public static function draft(
        string $title,
        ?string $description,
        ?string $category,
        int $wpAttachmentId,
        string $visibility,
        ?int $uploadedBy
    ): self {
        return new self(
            id: null,
            title: $title,
            description: $description,
            category: $category,
            wpAttachmentId: $wpAttachmentId,
            visibility: $visibility,
            uploadedBy: $uploadedBy,
            createdAt: null,
            updatedAt: null,
        );
    }

    public function isVisibleTo( string $viewerLevel ): bool {
        return Visibility::isAtLeast( $this->visibility, $viewerLevel );
    }
}
