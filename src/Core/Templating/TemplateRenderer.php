<?php

declare(strict_types=1);

namespace AssociationManager\Core\Templating;

defined( 'ABSPATH' ) || exit;

/**
 * Pure placeholder substitution - no storage or WordPress calls.
 * Shared by Notifications (email subject/body) and Certificates
 * (certificate HTML) - the "{token}" -> value scheme is the same
 * either way.
 */
final class TemplateRenderer {

    /**
     * @param array<string, string> $placeholders key => value, without braces
     */
    public function render( string $text, array $placeholders ): string {
        $replacements = [];

        foreach ( $placeholders as $key => $value ) {
            $replacements[ '{' . $key . '}' ] = $value;
        }

        return strtr( $text, $replacements );
    }
}
