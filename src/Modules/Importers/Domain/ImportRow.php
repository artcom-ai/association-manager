<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One record read from an import source, before any field mapping is
 * applied. $sourceUserId is whatever uniquely identifies this record
 * within its own source system (for MemberPress, the WP user id) -
 * deliberately distinct from $wpUserId, which is null for a source that
 * has no WordPress account relationship at all. $rawFields is the
 * source's own field keys (e.g. usermeta meta_key) mapped to raw values -
 * translating them into Association Manager field keys is
 * FieldMappingRegistry's job, not the source's.
 */
final class ImportRow {

    /**
     * @param array<string, mixed> $rawFields source field key => raw value
     */
    public function __construct(
        public readonly int $sourceUserId,
        public readonly ?int $wpUserId,
        public readonly ?string $email,
        public readonly array $rawFields,
    ) {
    }
}
