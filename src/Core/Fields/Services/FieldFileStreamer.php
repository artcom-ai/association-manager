<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Pure "given an attachment ID, send it as an HTTP response" - no
 * authorization logic at all. Callers (Modules\Members' admin-only
 * download handler, Modules\Portal's member-owns-this-record download
 * handler) are responsible for checking access before ever calling
 * this; Core has no way to know what "owns this record" means for a
 * given entity type (that's Module domain knowledge - see ADR-023
 * addendum), so it deliberately isn't attempted here.
 */
final class FieldFileStreamer {

    /**
     * Ends the request (exit) on success, same as any other
     * file-serving endpoint in this codebase (ImportPage's CSV export,
     * etc.) - there is no "after" for a caller to continue into.
     */
    public function stream( int $attachmentId ): void {
        $filePath = get_attached_file( $attachmentId );

        if ( $filePath === false || ! file_exists( $filePath ) ) {
            wp_die( esc_html__( 'File not found.', 'association-manager' ), '', [ 'response' => 404 ] );
        }

        $fileType = wp_check_filetype( $filePath );
        $mimeType = $fileType['type'] !== '' ? $fileType['type'] : 'application/octet-stream';

        nocache_headers();
        header( 'Content-Type: ' . $mimeType );
        header( 'Content-Disposition: inline; filename="' . basename( $filePath ) . '"' );
        header( 'Content-Length: ' . (string) filesize( $filePath ) );

        readfile( $filePath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming an existing WP attachment path, not arbitrary user input; WP_Filesystem::get_contents() reads the whole file into memory, which is worse for a large-file HTTP download than readfile()'s chunked streaming.

        exit;
    }
}
