<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Cli;

use AssociationManager\Modules\Importers\ImportSourceRegistry;
use AssociationManager\Modules\Importers\Services\MemberImportService;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * `wp association-manager import <source> [--dry-run] [--commit]`. Only
 * ever registered by ImportersModule::boot() behind a class_exists(
 * 'WP_CLI') guard - this file itself is safe to autoload on a request
 * where WP-CLI isn't present, since it only references the WP_CLI class
 * inside method bodies, never at load time.
 */
final class ImportCommand {

    public function __construct(
        private readonly ImportSourceRegistry $sources,
        private readonly MemberImportService $importService,
    ) {
    }

    /**
     * @param string[] $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke( array $args, array $assocArgs ): void {
        $sourceKey = $args[0] ?? null;

        if ( $sourceKey === null ) {
            WP_CLI::error( 'Usage: wp association-manager import <source> [--dry-run] [--commit]' );
            return;
        }

        $source = $this->sources->get( $sourceKey );

        if ( $source === null ) {
            WP_CLI::error( "Unknown import source \"{$sourceKey}\"." );
            return;
        }

        $commit = isset( $assocArgs['commit'] );

        if ( ! $commit && ! isset( $assocArgs['dry-run'] ) ) {
            WP_CLI::log( 'Neither --dry-run nor --commit given; defaulting to --dry-run.' );
        }

        $summary = $this->importService->run( $source, $commit );

        WP_CLI::log( "Source: {$summary->sourceSystem}" );
        WP_CLI::log( 'Mode: ' . ( $summary->dryRun ? 'dry-run' : 'commit' ) );
        WP_CLI::log( "Rows found: {$summary->totalRows()}" );
        WP_CLI::log( ( $summary->dryRun ? 'Would create' : 'Created' ) . ": {$summary->createdCount()}" );
        WP_CLI::log( ( $summary->dryRun ? 'Would update' : 'Updated' ) . ": {$summary->updatedCount()}" );

        $missing = $summary->allMissingFields();
        WP_CLI::log( 'Missing required fields: ' . ( $missing === [] ? 'none' : implode( ', ', $missing ) ) );

        $unmapped = $summary->allUnmappedKeys();
        WP_CLI::log( 'Unmapped source fields: ' . ( $unmapped === [] ? 'none' : implode( ', ', $unmapped ) ) );

        $conflictRows = $summary->rowsWithConflicts();

        if ( $conflictRows === [] ) {
            WP_CLI::log( 'Conflicts: none' );
        } else {
            WP_CLI::log( 'Conflicts:' );
            foreach ( $conflictRows as $row ) {
                WP_CLI::log( "  user {$row->sourceUserId}: " . implode( '; ', $row->conflicts ) );
            }
        }

        WP_CLI::success( $summary->dryRun ? 'Dry run complete.' : 'Import committed.' );
    }
}
