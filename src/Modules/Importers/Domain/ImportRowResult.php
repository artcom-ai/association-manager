<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The outcome of processing one ImportRow, in both dry-run and commit
 * mode - the shape is identical either way so a dry-run report and an
 * actual commit report can be rendered by the same code.
 */
final class ImportRowResult {

    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';

    /**
     * @param string[] $missingRequiredFields Association Manager field keys required but not resolvable from this row
     * @param string[] $unmappedSourceKeys    source field keys present on the row with no registered FieldMapping
     * @param string[] $conflicts             human-readable "field: old -> new" descriptions of values this row would overwrite
     */
    public function __construct(
        public readonly int $sourceUserId,
        public readonly string $action,
        public readonly array $missingRequiredFields,
        public readonly array $unmappedSourceKeys,
        public readonly array $conflicts,
        public readonly ?int $memberId = null,
    ) {
    }
}
