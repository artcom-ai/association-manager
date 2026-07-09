<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Admin;

use AssociationManager\Core\Admin\AdminPageInterface;
use AssociationManager\Core\Admin\DashboardPage;
use AssociationManager\Modules\Importers\Domain\ImportSummary;
use AssociationManager\Modules\Importers\ImportSourceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Deliberately minimal: pick a source, run a dry-run or a commit, see the
 * report. No mapping editor here - field mappings are implementation
 * config (FieldMappingRegistry), not something this MVP admin page edits.
 */
final class ImportPage implements AdminPageInterface {

    public const SLUG = 'association-manager-import';

    private const REPORT_TRANSIENT_PREFIX = 'am_import_last_report_';

    public function __construct(
        private readonly ImportSourceRegistry $sources
    ) {
    }

    public function slug(): string {
        return self::SLUG;
    }

    public function parentSlug(): string {
        return DashboardPage::SLUG;
    }

    public function pageTitle(): string {
        return __( 'Import Members', 'association-manager' );
    }

    public function menuTitle(): string {
        return __( 'Import', 'association-manager' );
    }

    public function capability(): string {
        return 'manage_options';
    }

    public static function storeReport( ImportSummary $summary ): void {
        set_transient(
            self::REPORT_TRANSIENT_PREFIX . get_current_user_id(),
            [
				'sourceSystem' => $summary->sourceSystem,
				'dryRun'       => $summary->dryRun,
				'total'        => $summary->totalRows(),
				'created'      => $summary->createdCount(),
				'updated'      => $summary->updatedCount(),
				'missing'      => $summary->allMissingFields(),
				'unmapped'     => $summary->allUnmappedKeys(),
				'conflicts'    => array_map(
					static fn ( $row ): array => [
						'sourceUserId' => $row->sourceUserId,
						'conflicts'    => $row->conflicts,
                    ],
					$summary->rowsWithConflicts()
				),
			],
            5 * MINUTE_IN_SECONDS
        );
    }

    public function render(): void {
        $sources    = $this->sources->all();
        $report     = get_transient( self::REPORT_TRANSIENT_PREFIX . get_current_user_id() );
        $noticeType = isset( $_GET['am_notice'] ) ? sanitize_text_field( (string) $_GET['am_notice'] ) : null;

        require AM_PLUGIN_DIR . 'templates/admin/import.php';
    }
}
