<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Repositories;

use AssociationManager\Modules\Members\Domain\Member;

defined('ABSPATH') || exit;

interface MemberRepositoryInterface
{
    public function find(int $id): ?Member;

    /**
     * @return Member[]
     */
    public function all(): array;

    public function insert(Member $member): int;

    public function update(Member $member): void;
}
