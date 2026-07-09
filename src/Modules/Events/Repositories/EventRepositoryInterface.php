<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Events\Repositories;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Events\Domain\Event;

defined( 'ABSPATH' ) || exit;

interface EventRepositoryInterface {

    public function find( int $id ): ?Event;

    /**
     * @return Event[]
     */
    public function all(): array;

    /**
     * @return PaginatedResult<Event>
     */
    public function paginate( PaginationParams $params ): PaginatedResult;

    /**
     * Published events whose start time hasn't passed yet, soonest first.
     *
     * @return Event[]
     */
    public function upcomingPublished(): array;

    /**
     * @return PaginatedResult<Event>
     */
    public function paginateUpcomingPublished( PaginationParams $params ): PaginatedResult;

    public function insert( Event $event ): int;

    public function update( Event $event ): void;
}
