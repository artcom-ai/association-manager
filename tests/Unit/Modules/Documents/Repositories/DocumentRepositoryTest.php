<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Documents\Repositories;

use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Repositories\DocumentRepository;
use AssociationManager\Tests\Support\TestCase;

final class DocumentRepositoryTest extends TestCase
{
    private DocumentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DocumentRepository();
    }

    public function testInsertAndFind(): void
    {
        $id = $this->repository->insert(
            Document::draft('Bylaws', 'The association bylaws', 'governance', 42, Visibility::VISIBILITY_PUBLIC, 7)
        );

        $found = $this->repository->find($id);

        $this->assertNotNull($found);
        $this->assertSame('Bylaws', $found->title);
        $this->assertSame(42, $found->wpAttachmentId);
        $this->assertSame(Visibility::VISIBILITY_PUBLIC, $found->visibility);
        $this->assertSame(7, $found->uploadedBy);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->find(999));
    }

    public function testAllReturnsEveryDocument(): void
    {
        $this->repository->insert(Document::draft('A', null, null, 1, Visibility::VISIBILITY_PUBLIC, null));
        $this->repository->insert(Document::draft('B', null, null, 2, Visibility::VISIBILITY_ADMIN, null));

        $this->assertCount(2, $this->repository->all());
    }

    public function testUpdatePersistsMetadataChangesButNotTheAttachment(): void
    {
        $id = $this->repository->insert(
            Document::draft('Old title', 'Old desc', 'old-cat', 42, Visibility::VISIBILITY_ADMIN, 7)
        );
        $document = $this->repository->find($id);

        $updated = $document->withMetadata('New title', 'New desc', 'new-cat', Visibility::VISIBILITY_PUBLIC);
        $this->repository->update($updated);

        $found = $this->repository->find($id);
        $this->assertSame('New title', $found->title);
        $this->assertSame('New desc', $found->description);
        $this->assertSame('new-cat', $found->category);
        $this->assertSame(Visibility::VISIBILITY_PUBLIC, $found->visibility);
        $this->assertSame(42, $found->wpAttachmentId, 'the underlying file must not change on a metadata update');
    }

    public function testDeleteRemovesTheRow(): void
    {
        $id = $this->repository->insert(Document::draft('A', null, null, 1, Visibility::VISIBILITY_PUBLIC, null));

        $this->repository->delete($id);

        $this->assertNull($this->repository->find($id));
    }
}
