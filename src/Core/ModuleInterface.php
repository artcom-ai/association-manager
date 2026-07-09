<?php

declare(strict_types=1);

namespace AssociationManager\Core;

defined( 'ABSPATH' ) || exit;

interface ModuleInterface {

    public function name(): string;

    public function register( Container $container ): void;

    public function boot( Container $container ): void;
}
