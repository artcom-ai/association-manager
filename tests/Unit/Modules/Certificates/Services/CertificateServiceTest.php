<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Certificates\Services;

use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Modules\Certificates\Domain\CertificateStatus;
use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepository;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepository;
use AssociationManager\Modules\Certificates\Services\CertificateGenerator;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Tests\Support\TestCase;

final class CertificateServiceTest extends TestCase
{
    private CertificateTemplateRepository $templates;
    private CertificateRepository $certificates;
    private CertificateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templates = new CertificateTemplateRepository();
        $this->certificates = new CertificateRepository();

        $this->service = new CertificateService(
            $this->templates,
            $this->certificates,
            new CertificateGenerator(new TemplateRenderer()),
        );

        $this->setNow('2026-01-01 00:00:00');
    }

    private function saveDefaultTemplate(): void
    {
        $this->templates->save(new CertificateTemplate(
            null,
            'membership',
            'Membership Certificate',
            '<html><body>{member_number}</body></html>'
        ));
    }

    public function testCreateDraftGeneratesAndStoresWithoutFiringNotification(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 77;

        $certificate = $this->service->createDraft(5, 'membership', ['member_number' => 'M-001'], 9);

        $this->assertSame(5, $certificate->memberId);
        $this->assertSame('membership', $certificate->typeKey);
        $this->assertSame(77, $certificate->wpAttachmentId);
        $this->assertSame(CertificateStatus::DRAFT, $certificate->status);
        $this->assertSame(9, $certificate->issuedBy);

        $this->assertCount(1, $this->certificates->allForMember(5));
        $this->assertCount(0, $this->firedActionsNamed('association_manager_certificate_issued'));
    }

    public function testCreateDraftThrowsWhenTemplateIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->createDraft(5, 'no-such-type', [], null);
    }

    public function testCreateDraftThrowsWhenSideloadFails(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = new \WP_Error('sideload_error', 'Could not sideload file.');

        $this->expectException(\RuntimeException::class);

        $this->service->createDraft(5, 'membership', [], null);
    }

    public function testIssueTransitionsDraftToIssuedAndFiresAction(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 77;
        $draft = $this->service->createDraft(5, 'membership', ['member_number' => 'M-001'], 9);

        $this->setNow('2026-01-02 00:00:00');
        $issued = $this->service->issue($draft->id);

        $this->assertSame(CertificateStatus::ISSUED, $issued->status);
        $this->assertSame('2026-01-02 00:00:00', $issued->issuedAt);

        $fired = $this->firedActionsNamed('association_manager_certificate_issued');
        $this->assertCount(1, $fired);
        $this->assertSame($issued->id, $fired[0]['args'][0]->id);
    }

    public function testIssueOfAlreadyIssuedCertificateThrows(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 77;
        $draft = $this->service->createDraft(5, 'membership', [], null);
        $this->service->issue($draft->id);

        $this->expectException(\LogicException::class);

        $this->service->issue($draft->id);
    }

    public function testIssueOfUnknownCertificateThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->issue(999);
    }

    public function testRevokeTransitionsIssuedToRevoked(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 77;
        $draft = $this->service->createDraft(5, 'membership', [], null);
        $this->service->issue($draft->id);

        $revoked = $this->service->revoke($draft->id);

        $this->assertSame(CertificateStatus::REVOKED, $revoked->status);
    }

    public function testRevokeOfADraftCertificateThrows(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 77;
        $draft = $this->service->createDraft(5, 'membership', [], null);

        $this->expectException(\LogicException::class);

        $this->service->revoke($draft->id);
    }

    public function testIssuedForMemberExcludesDraftsAndRevokedCertificates(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 1;
        $stillDraft = $this->service->createDraft(5, 'membership', [], null);

        $GLOBALS['__am_test_media_sideload_result'] = 2;
        $toRevoke = $this->service->createDraft(5, 'membership', [], null);
        $this->service->issue($toRevoke->id);
        $this->service->revoke($toRevoke->id);

        $GLOBALS['__am_test_media_sideload_result'] = 3;
        $staysIssued = $this->service->createDraft(5, 'membership', [], null);
        $this->service->issue($staysIssued->id);

        $visible = $this->service->issuedForMember(5);

        $this->assertCount(1, $visible);
        $this->assertSame($staysIssued->id, $visible[0]->id);
    }

    public function testAllReturnsEveryCertificateRegardlessOfStatus(): void
    {
        $this->saveDefaultTemplate();
        $GLOBALS['__am_test_media_sideload_result'] = 1;
        $this->service->createDraft(5, 'membership', [], null);
        $GLOBALS['__am_test_media_sideload_result'] = 2;
        $this->service->createDraft(6, 'membership', [], null);

        $this->assertCount(2, $this->service->all());
    }
}
