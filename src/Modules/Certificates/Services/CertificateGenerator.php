<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Certificates\Services;

use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;
use Dompdf\Dompdf;
use Dompdf\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Pure PDF rendering - no WordPress calls, no storage. Renders the
 * admin-edited HTML template against placeholders, then converts it to
 * PDF bytes via dompdf. Saving those bytes as a WP attachment is
 * CertificateService's job, kept separate so this class stays fully
 * unit-testable.
 */
final class CertificateGenerator {

    public function __construct(
        private readonly TemplateRenderer $renderer
    ) {
    }

    /**
     * @param array<string, string> $placeholders key => value, without braces
     */
    public function generate( CertificateTemplate $template, array $placeholders ): string {
        $html = $this->renderer->render( $template->htmlBody, $placeholders );

        $options = new Options();
        $options->set( 'isRemoteEnabled', false );

        $dompdf = new Dompdf( $options );
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        return $dompdf->output();
    }
}
