<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\FieldValidationException;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Modules\Importers\Domain\ImportRow;
use AssociationManager\Modules\Importers\Domain\ImportRowResult;
use AssociationManager\Modules\Importers\Domain\ImportSummary;
use AssociationManager\Modules\Importers\FieldMappingRegistry;
use AssociationManager\Modules\Importers\ImportSourceInterface;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;
use AssociationManager\Modules\Members\Services\MemberService;

defined( 'ABSPATH' ) || exit;

/**
 * The generic import orchestrator - knows nothing about MemberPress or
 * any other concrete source, only ImportSourceInterface/ImportRow/
 * FieldMapping. Dry-run and commit share the exact same row-processing
 * logic (mapping, missing-field detection, conflict detection); the only
 * difference is whether writes actually happen, so a dry-run report is
 * guaranteed to describe what a commit run would really do.
 */
final class MemberImportService {

    private const ENTITY_TYPE = 'member';

    public function __construct(
        private readonly FieldMappingRegistry $fieldMappings,
        private readonly FieldRegistry $fieldRegistry,
        private readonly FieldValueService $fieldValueService,
        private readonly MemberRepositoryInterface $members,
        private readonly MemberService $memberService,
    ) {
    }

    public function run( ImportSourceInterface $source, bool $commit ): ImportSummary {
        $sourceSystem = $source->key();

        $mappingsByKey = [];
        foreach ( $this->fieldMappings->forSource( $sourceSystem ) as $mapping ) {
            $mappingsByKey[ $mapping->sourceKey ] = $mapping;
        }

        $requiredFieldKeys = array_map(
            static fn ( FieldDefinition $field ): string => $field->key,
            array_filter(
                $this->fieldRegistry->forEntityType( self::ENTITY_TYPE ),
                static fn ( FieldDefinition $field ): bool => $field->required
            )
        );

        $importedAt = current_time( 'mysql' );

        $rows = [];

        foreach ( $source->fetchRows() as $row ) {
            $rows[] = $this->processRow( $row, $sourceSystem, $mappingsByKey, $requiredFieldKeys, $commit, $importedAt );
        }

        return new ImportSummary( $sourceSystem, ! $commit, $rows );
    }

    /**
     * @param array<string, \AssociationManager\Modules\Importers\Domain\FieldMapping> $mappingsByKey
     * @param string[] $requiredFieldKeys
     */
    private function processRow(
        ImportRow $row,
        string $sourceSystem,
        array $mappingsByKey,
        array $requiredFieldKeys,
        bool $commit,
        string $importedAt
    ): ImportRowResult {
        $mappedValues = [];
        $unmapped     = [];

        foreach ( $row->rawFields as $sourceKey => $value ) {
            $mapping = $mappingsByKey[ $sourceKey ] ?? null;

            if ( $mapping === null ) {
                $unmapped[] = $sourceKey;
                continue;
            }

            $mappedValues[ $mapping->targetFieldKey ] = $mapping->apply( $value );
        }

        $missing = [];
        foreach ( $requiredFieldKeys as $fieldKey ) {
            $value = $mappedValues[ $fieldKey ] ?? null;

            if ( $value === null || $value === '' ) {
                $missing[] = $fieldKey;
            }
        }

        // Two idempotency keys, checked in order: source-tagged first (a
        // previous run of this exact importer), falling back to the WP
        // account link (a member that already existed - e.g. created
        // manually, or by a different importer - before this one ever
        // ran). Either match means "update", not "create".
        $existing = $this->members->findBySource( $sourceSystem, $row->sourceUserId )
            ?? ( $row->wpUserId !== null ? $this->members->findByWpUserId( $row->wpUserId ) : null );

        $conflicts = $existing !== null ? $this->detectConflicts( $existing, $row, $mappedValues ) : [];

        $action   = $existing !== null ? ImportRowResult::ACTION_UPDATE : ImportRowResult::ACTION_CREATE;
        $memberId = $existing?->id;

        if ( $commit ) {
            $member = $existing === null
                ? $this->memberService->createFromImport( $row->wpUserId, $row->email, $sourceSystem, $row->sourceUserId, $importedAt, $row->firstName, $row->lastName )
                : $this->memberService->applyImport( $existing->requireId(), $row->email, $sourceSystem, $row->sourceUserId, $importedAt, $row->firstName, $row->lastName );

            $memberId = $member->requireId();

            if ( $mappedValues !== [] ) {
                try {
                    $this->fieldValueService->save( self::ENTITY_TYPE, $memberId, $mappedValues );
                } catch ( FieldValidationException $e ) {
                    foreach ( array_keys( $e->errors() ) as $fieldKey ) {
                        $conflicts[] = "{$fieldKey}: validation failed and was not saved";
                    }
                }
            }
        }

        return new ImportRowResult( $row->sourceUserId, $action, $missing, $unmapped, $conflicts, $memberId );
    }

    /**
     * @param array<string, mixed> $mappedValues
     * @return string[]
     */
    private function detectConflicts( Member $existing, ImportRow $row, array $mappedValues ): array {
        $conflicts = [];

        if ( $row->email !== null && $existing->email !== null && $existing->email !== $row->email ) {
            $conflicts[] = "email: \"{$existing->email}\" -> \"{$row->email}\"";
        }

        $currentValues = $this->fieldValueService->valuesFor( self::ENTITY_TYPE, $existing->requireId() );

        foreach ( $mappedValues as $fieldKey => $value ) {
            $current = $currentValues[ $fieldKey ] ?? null;
            $new     = $value !== null ? (string) $value : null;

            if ( $current !== null && $new !== null && $current !== $new ) {
                $conflicts[] = "{$fieldKey}: \"{$current}\" -> \"{$new}\"";
            }
        }

        return $conflicts;
    }
}
