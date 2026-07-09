<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Importers;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the WP-CLI command and the admin page resolve a source by its
 * string key (e.g. "memberpress") without knowing about any concrete
 * ImportSourceInterface implementation directly - the piece that makes
 * "wp association-manager import <source>" source-agnostic.
 */
final class ImportSourceRegistry {

    /**
     * @var array<string, ImportSourceInterface>
     */
    private array $sources = [];

    public function register( ImportSourceInterface $source ): void {
        $this->sources[ $source->key() ] = $source;
    }

    public function get( string $key ): ?ImportSourceInterface {
        return $this->sources[ $key ] ?? null;
    }

    /**
     * @return ImportSourceInterface[]
     */
    public function all(): array {
        return array_values( $this->sources );
    }
}
