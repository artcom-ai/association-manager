<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers;

use AssociationManager\Modules\Importers\Domain\FieldMapping;

defined( 'ABSPATH' ) || exit;

/**
 * Extension point for source-specific field mappings, same shape as
 * Core\Fields\FieldRegistry - an integration (MemberPressUserSource's
 * own defaults, if any) or a per-client implementation fetches this from
 * the container and calls register() for a given source system key. Kept
 * in the Importers module, not Core, since "how an external source's
 * fields translate to Association Manager fields" is specific to the
 * importing capability, not a generic Core concern.
 */
final class FieldMappingRegistry {

    /**
     * @var array<string, array<string, FieldMapping>>
     */
    private array $mappings = [];

    public function register( string $sourceSystem, FieldMapping $mapping ): void {
        $this->mappings[ $sourceSystem ][ $mapping->sourceKey ] = $mapping;
    }

    /**
     * @return FieldMapping[]
     */
    public function forSource( string $sourceSystem ): array {
        return array_values( $this->mappings[ $sourceSystem ] ?? [] );
    }
}
