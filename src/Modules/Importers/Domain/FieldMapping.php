<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One source-field -> Association Manager dynamic-field translation rule.
 * This is the "configurable field mapping layer" the importer requires
 * instead of hardcoded SQL column selection: an implementation (or a
 * future admin-facing mapping editor) registers these against
 * FieldMappingRegistry rather than any importer code needing to know
 * which specific meta keys matter.
 */
final class FieldMapping {

    public function __construct(
        public readonly string $sourceKey,
        public readonly string $targetFieldKey,
        private readonly ?\Closure $transform = null,
    ) {
    }

    public function apply( mixed $rawValue ): mixed {
        return $this->transform !== null ? ( $this->transform )( $rawValue ) : $rawValue;
    }
}
