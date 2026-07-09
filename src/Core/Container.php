<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined( 'ABSPATH' ) || exit;

final class Container {

    /**
     * @var array<string, mixed>
     */
    private array $services = [];

    public function set( string $id, mixed $service ): void {
        $this->services[ $id ] = $service;
    }

    public function get( string $id ): mixed {
        if ( ! $this->has( $id ) ) {
            throw new \RuntimeException( "Service not found: {$id}" );
        }

        return $this->services[ $id ];
    }

    public function has( string $id ): bool {
        return array_key_exists( $id, $this->services );
    }
}
