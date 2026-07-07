<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Services;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined('ABSPATH') || exit;

final class MemberService
{
    public function __construct(
        private readonly MemberRepositoryInterface $repository
    ) {
    }

    public function register(?int $wpUserId, ?string $membershipType): Member
    {
        $id = $this->repository->insert(Member::draft($wpUserId, $membershipType));

        return $this->mustFind($id);
    }

    public function approve(int $memberId): Member
    {
        $approved = $this->mustFind($memberId)->approve(current_time('mysql'));

        $this->repository->update($approved);

        return $approved;
    }

    public function suspend(int $memberId): Member
    {
        $suspended = $this->mustFind($memberId)->suspend();

        $this->repository->update($suspended);

        return $suspended;
    }

    public function find(int $id): ?Member
    {
        return $this->repository->find($id);
    }

    /**
     * @return Member[]
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return PaginatedResult<Member>
     */
    public function paginate(PaginationParams $params): PaginatedResult
    {
        return $this->repository->paginate($params);
    }

    /**
     * @return PaginatedResult<Member>
     */
    public function paginateByStatus(string $status, PaginationParams $params): PaginatedResult
    {
        return $this->repository->paginateByStatus($status, $params);
    }

    private function mustFind(int $id): Member
    {
        $member = $this->repository->find($id);

        if ($member === null) {
            throw new \RuntimeException("Member not found: {$id}");
        }

        return $member;
    }
}
