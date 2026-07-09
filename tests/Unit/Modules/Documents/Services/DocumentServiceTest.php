<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Documents\Services;

use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Repositories\DocumentRepository;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Tests\Support\TestCase;

final class DocumentServiceTest extends TestCase
{
    private DocumentRepository $repository;
    private DocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DocumentRepository();
        $this->service = new DocumentService($this->repository);
    }

    public function testListVisibleToFiltersByVisibility(): void
    {
        $this->repository->insert(Document::draft('Public doc', null, null, 1, Visibility::VISIBILITY_PUBLIC, null));
        $this->repository->insert(Document::draft('Private doc', null, null, 2, Visibility::VISIBILITY_PRIVATE, null));
        $this->repository->insert(Document::draft('Admin doc', null, null, 3, Visibility::VISIBILITY_ADMIN, null));

        $publicView = $this->service->listVisibleTo(Visibility::VISIBILITY_PUBLIC);
        $this->assertCount(1, $publicView);
        $this->assertSame('Public doc', $publicView[0]->title);

        $privateView = $this->service->listVisibleTo(Visibility::VISIBILITY_PRIVATE);
        $this->assertCount(2, $privateView);

        $adminView = $this->service->listVisibleTo(Visibility::VISIBILITY_ADMIN);
        $this->assertCount(3, $adminView);
    }

    public function testUploadPersistsAndFiresDocumentPublishedAction(): void
    {
        $GLOBALS['__am_test_media_upload_result'] = 55;

        $document = $this->service->upload(
            'Membership form',
            'A blank form',
            'forms',
            Visibility::VISIBILITY_PUBLIC,
            ['name' => 'form.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 100],
            7
        );

        $this->assertSame('Membership form', $document->title);
        $this->assertSame(55, $document->wpAttachmentId);

        $fired = $this->firedActionsNamed('association_manager_document_published');
        $this->assertCount(1, $fired);
        $this->assertSame($document->id, $fired[0]['args'][0]->id);
    }

    public function testUploadThrowsOnMediaHandleUploadFailure(): void
    {
        $GLOBALS['__am_test_media_upload_result'] = new \WP_Error('upload_error', 'The file could not be uploaded.');

        $this->expectException(\RuntimeException::class);

        $this->service->upload(
            'Bad upload',
            null,
            null,
            Visibility::VISIBILITY_PUBLIC,
            ['name' => 'bad.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 100],
            null
        );
    }

    public function testDeleteRemovesRowAndDeletesAttachment(): void
    {
        $id = $this->repository->insert(
            Document::draft('Doc', null, null, 99, Visibility::VISIBILITY_PUBLIC, null)
        );

        $this->service->delete($id);

        $this->assertNull($this->repository->find($id));
        $this->assertSame([99], $this->deletedAttachments());
    }

    public function testDeleteOfMissingDocumentIsANoOp(): void
    {
        $this->service->delete(999);

        $this->assertSame([], $this->deletedAttachments());
    }
}
