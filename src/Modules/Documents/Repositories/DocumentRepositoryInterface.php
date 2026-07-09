<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Documents\Repositories;

use AssociationManager\Modules\Documents\Domain\Document;

defined( 'ABSPATH' ) || exit;

interface DocumentRepositoryInterface {

    public function find( int $id ): ?Document;

    /**
     * @return Document[]
     */
    public function all(): array;

    public function insert( Document $document ): int;

    public function delete( int $id ): void;
}
