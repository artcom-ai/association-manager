<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers\Repositories;

use AssociationManager\Modules\Importers\Domain\FieldMapping;

defined( 'ABSPATH' ) || exit;

interface FieldMappingRepositoryInterface {

    /**
     * @return FieldMapping[]
     */
    public function all( string $sourceSystem ): array;

    public function find( string $sourceSystem, string $sourceKey ): ?FieldMapping;

    /**
     * Upsert keyed by (source_system, source_key).
     */
    public function save( string $sourceSystem, FieldMapping $mapping ): void;

    /**
     * Every distinct source_system that has at least one mapping stored -
     * lets the boot-time loader populate FieldMappingRegistry without
     * Importers needing to hardcode "memberpress" or any other source.
     *
     * @return string[]
     */
    public function allSourceSystems(): array;
}
