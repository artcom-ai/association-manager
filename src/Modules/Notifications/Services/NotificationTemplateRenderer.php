<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Pure placeholder substitution - no storage or WordPress calls.
 */
final class NotificationTemplateRenderer {

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
