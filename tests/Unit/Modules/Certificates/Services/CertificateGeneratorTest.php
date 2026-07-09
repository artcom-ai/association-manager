<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Certificates\Services;

use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;
use AssociationManager\Modules\Certificates\Services\CertificateGenerator;
use AssociationManager\Tests\Support\TestCase;

final class CertificateGeneratorTest extends TestCase
{
    private CertificateGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new CertificateGenerator(new TemplateRenderer());
    }

    public function testGeneratesRealPdfBytesFromTemplate(): void
    {
        $template = new CertificateTemplate(
            id: 1,
            typeKey: 'membership',
            name: 'Membership Certificate',
            htmlBody: '<html><body><h1>{member_number}</h1><p>{membership_type}</p></body></html>',
        );

        $pdf = $this->generator->generate($template, [
            'member_number' => 'M-042',
            'membership_type' => 'individual',
        ]);

        $this->assertIsString($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function testUnmatchedPlaceholderIsLeftAsIs(): void
    {
        $template = new CertificateTemplate(
            id: 1,
            typeKey: 'membership',
            name: 'Membership Certificate',
            htmlBody: '<html><body>{unknown_token}</body></html>',
        );

        // No exception, no substitution failure - dompdf still renders a valid PDF.
        $pdf = $this->generator->generate($template, []);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
