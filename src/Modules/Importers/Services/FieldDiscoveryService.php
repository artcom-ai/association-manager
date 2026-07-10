<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\Repositories\FieldDefinitionRepositoryInterface;
use AssociationManager\Modules\Importers\Domain\FieldMapping;
use AssociationManager\Modules\Importers\Repositories\FieldMappingRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a dry-run's "unmapped source fields" list into real, persistent
 * Member Fields - the "read and recreate the custom fields used in
 * MemberPress" capability. Every created field is deliberately a plain
 * text field with admin-only visibility and a best-effort humanized
 * label, not a finished configuration - it's a starting point the admin
 * is expected to review and refine (real label, type, visibility) via
 * the Member Fields admin page, same as any other field.
 */
final class FieldDiscoveryService {

    private const ENTITY_TYPE = 'member';

    public function __construct(
        private readonly FieldDefinitionRepositoryInterface $fieldDefinitions,
        private readonly FieldMappingRepositoryInterface $fieldMappings,
    ) {
    }

    /**
     * Skips any source key that already resolves to an existing field
     * (by the sanitized key) or an existing mapping - so re-running this
     * after an admin has already reviewed/renamed a field doesn't
     * silently recreate or overwrite their edits.
     *
     * @param string[] $unmappedKeys
     */
    public function createFieldsFromUnmappedKeys( string $sourceSystem, array $unmappedKeys ): int {
        $created = 0;

        foreach ( $unmappedKeys as $sourceKey ) {
            if ( $this->fieldMappings->find( $sourceSystem, $sourceKey ) !== null ) {
                continue;
            }

            $fieldKey = $this->sanitizeFieldKey( $sourceKey );

            if ( $fieldKey === '' ) {
                continue;
            }

            if ( $this->fieldDefinitions->find( self::ENTITY_TYPE, $fieldKey ) === null ) {
                $this->fieldDefinitions->save(
                    self::ENTITY_TYPE,
                    new FieldDefinition(
                        key: $fieldKey,
                        label: $this->humanize( $sourceKey ),
                        type: FieldDefinition::TYPE_TEXT,
                        visibility: FieldDefinition::VISIBILITY_ADMIN,
                    )
                );
            }

            $this->fieldMappings->save( $sourceSystem, new FieldMapping( $sourceKey, $fieldKey ) );

            ++$created;
        }

        return $created;
    }

    private function sanitizeFieldKey( string $sourceKey ): string {
        // Hyphens are converted, not stripped - a source key like
        // "mepr-tilefono-epikoinonias" must not collapse into an
        // unreadable "meprtilefonoepikoinonias".
        $key = str_replace( '-', '_', strtolower( $sourceKey ) );

        return preg_replace( '/[^a-z0-9_]/', '', $key ) ?? '';
    }

    /**
     * Best-effort readable label - strips a leading "mepr-"/"mepr_"
     * prefix (meaningful only for the source's own internal naming, not
     * to a human reading the field list) and title-cases the rest. Not a
     * translation - the admin is expected to correct this, especially
     * for source keys that are themselves a transliteration (e.g.
     * "eidikotita") rather than English.
     */
    private function humanize( string $sourceKey ): string {
        $stripped = preg_replace( '/^mepr[-_]/', '', $sourceKey ) ?? $sourceKey;
        $spaced   = str_replace( [ '_', '-' ], ' ', $stripped );

        return ucwords( trim( $spaced ) );
    }
}
