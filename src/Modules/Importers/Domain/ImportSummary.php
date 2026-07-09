<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregate report over every row an import run touched - what dry-run
 * mode shows the admin before anything is written, and what a commit run
 * reports afterward (see requirement: "show how many users will be
 * imported, missing fields, unmapped fields, conflicts").
 */
final class ImportSummary {

    /**
     * @param ImportRowResult[] $rows
     */
    public function __construct(
        public readonly string $sourceSystem,
        public readonly bool $dryRun,
        public readonly array $rows,
    ) {
    }

    public function totalRows(): int {
        return count( $this->rows );
    }

    public function createdCount(): int {
        return count( array_filter( $this->rows, static fn ( ImportRowResult $r ): bool => $r->action === ImportRowResult::ACTION_CREATE ) );
    }

    public function updatedCount(): int {
        return count( array_filter( $this->rows, static fn ( ImportRowResult $r ): bool => $r->action === ImportRowResult::ACTION_UPDATE ) );
    }

    /**
     * @return string[]
     */
    public function allMissingFields(): array {
        return $this->dedupedAcrossRows( static fn ( ImportRowResult $r ): array => $r->missingRequiredFields );
    }

    /**
     * @return string[]
     */
    public function allUnmappedKeys(): array {
        return $this->dedupedAcrossRows( static fn ( ImportRowResult $r ): array => $r->unmappedSourceKeys );
    }

    /**
     * @return ImportRowResult[] only the rows that have at least one conflict
     */
    public function rowsWithConflicts(): array {
        return array_values( array_filter( $this->rows, static fn ( ImportRowResult $r ): bool => $r->conflicts !== [] ) );
    }

    /**
     * @param \Closure(ImportRowResult): string[] $extractor
     * @return string[]
     */
    private function dedupedAcrossRows( \Closure $extractor ): array {
        $all = [];

        foreach ( $this->rows as $row ) {
            foreach ( $extractor( $row ) as $item ) {
                $all[ $item ] = true;
            }
        }

        return array_keys( $all );
    }
}
