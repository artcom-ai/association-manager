<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Portal\Services;

use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepository;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepository;
use AssociationManager\Modules\Certificates\Services\CertificateGenerator;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Repositories\DocumentRepository;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Portal\Services\PortalService;
use AssociationManager\Tests\Support\TestCase;

final class PortalServiceTest extends TestCase
{
    private MemberRepository $members;
    private DocumentRepository $documentRepository;
    private CertificateRepository $certificateRepository;
    private PortalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->members = new MemberRepository();
        $this->documentRepository = new DocumentRepository();
        $this->certificateRepository = new CertificateRepository();

        $this->service = new PortalService(
            $this->members,
            new DocumentService($this->documentRepository),
            new CertificateService(
                new CertificateTemplateRepository(),
                $this->certificateRepository,
                new CertificateGenerator(new TemplateRenderer()),
            ),
        );
    }

    public function testMemberForReturnsTheLinkedMember(): void
    {
        $id = $this->members->insert(Member::draft(42, 'individual'));

        $member = $this->service->memberFor(42);

        $this->assertNotNull($member);
        $this->assertSame($id, $member->id);
    }

    public function testMemberForReturnsNullWhenNoAccountIsLinked(): void
    {
        $this->assertNull($this->service->memberFor(999));
    }

    public function testVisibleDocumentsExcludesAdminOnlyDocuments(): void
    {
        $this->documentRepository->insert(Document::draft('Public', null, null, 1, Visibility::VISIBILITY_PUBLIC, null));
        $this->documentRepository->insert(Document::draft('Private', null, null, 2, Visibility::VISIBILITY_PRIVATE, null));
        $this->documentRepository->insert(Document::draft('Admin', null, null, 3, Visibility::VISIBILITY_ADMIN, null));

        $visible = $this->service->visibleDocuments();

        $this->assertCount(2, $visible);
    }

    public function testCertificatesForReturnsOnlyThatMembersCertificates(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $this->certificateRepository->insert(new Certificate(null, $id, 'membership', 1, '2026-01-01 00:00:00', null));
        $this->certificateRepository->insert(new Certificate(null, 999, 'membership', 2, '2026-01-01 00:00:00', null));

        $certificates = $this->service->certificatesFor($member);

        $this->assertCount(1, $certificates);
        $this->assertSame($id, $certificates[0]->memberId);
    }
}
