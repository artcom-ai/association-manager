<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers;

use AssociationManager\Modules\Importers\Domain\ImportRow;

defined( 'ABSPATH' ) || exit;

/**
 * The contract any importer (MemberPress today, a future CSV/API source
 * tomorrow) implements. MemberImportService is written entirely against
 * this interface - it never knows what "memberpress" means, only that
 * something can hand it a source system key and a list of rows.
 */
interface ImportSourceInterface {

    /**
     * Matches the key FieldMapping entries are registered under in
     * FieldMappingRegistry, and Member's source_system column value.
     */
    public function key(): string;

    public function label(): string;

    /**
     * @return ImportRow[]
     */
    public function fetchRows(): array;
}
