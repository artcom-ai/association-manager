<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;

defined( 'ABSPATH' ) || exit;

interface MemberRepositoryInterface {

    public function find( int $id ): ?Member;

    public function findByMemberNumber( string $memberNumber ): ?Member;

    /**
     * @return Member[]
     */
    public function all(): array;

    /**
     * @return PaginatedResult<Member>
     */
    public function paginate( PaginationParams $params ): PaginatedResult;

    /**
     * @return PaginatedResult<Member>
     */
    public function search( MemberSearchCriteria $criteria, PaginationParams $params ): PaginatedResult;

    /**
     * Coarse candidates for automatic expiry: active members whose
     * expires_at has already passed (before any grace-period check).
     *
     * @return Member[]
     */
    public function findExpiredCandidates( string $now ): array;

    public function insert( Member $member ): int;

    public function update( Member $member ): void;
}
