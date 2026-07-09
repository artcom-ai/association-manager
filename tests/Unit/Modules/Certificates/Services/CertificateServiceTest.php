<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Certificates\Services;

use AssociationManager\Core\Templating\TemplateRenderer;
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

    public function testIssueCreatesCertificateAndFiresAction(): void
    {
        $this->templates->save(new CertificateTemplate(
            null,
            'membership',
            'Membership Certificate',
            '<html><body>{member_number}</body></html>'
        ));

        $GLOBALS['__am_test_media_sideload_result'] = 77;

        $certificate = $this->service->issue(5, 'membership', ['member_number' => 'M-001'], 9);

        $this->assertSame(5, $certificate->memberId);
        $this->assertSame('membership', $certificate->typeKey);
        $this->assertSame(77, $certificate->wpAttachmentId);
        $this->assertSame(9, $certificate->issuedBy);

        $stored = $this->certificates->allForMember(5);
        $this->assertCount(1, $stored);

        $fired = $this->firedActionsNamed('association_manager_certificate_issued');
        $this->assertCount(1, $fired);
        $this->assertSame($certificate->id, $fired[0]['args'][0]->id);
    }

    public function testIssueThrowsWhenTemplateIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->issue(5, 'no-such-type', [], null);
    }

    public function testIssueThrowsWhenSideloadFails(): void
    {
        $this->templates->save(new CertificateTemplate(
            null,
            'membership',
            'Membership Certificate',
            '<html><body>x</body></html>'
        ));

        $GLOBALS['__am_test_media_sideload_result'] = new \WP_Error('sideload_error', 'Could not sideload file.');

        $this->expectException(\RuntimeException::class);

        $this->service->issue(5, 'membership', [], null);
    }
}
