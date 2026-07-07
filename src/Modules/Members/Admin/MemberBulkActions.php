<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Admin;

use AssociationManager\Modules\Members\Services\MemberService;

defined('ABSPATH') || exit;

final class MemberBulkActions
{
    public function __construct(
        private readonly MemberService $service
    ) {
    }

    /**
     * @param int[] $memberIds
     * @return array{succeeded: int[], failed: array<int, string>}
     */
    public function apply(string $action, array $memberIds, ?int $changedBy): array
    {
        $succeeded = [];
        $failed = [];

        foreach ($memberIds as $id) {
            try {
                match ($action) {
                    'activate' => $this->service->activateMember($id, $changedBy),
                    'suspend' => $this->service->suspendMember($id, $changedBy),
                    'archive' => $this->service->archiveMember($id, $changedBy),
                    default => throw new \InvalidArgumentException("Unknown bulk action \"{$action}\"."),
                };

                $succeeded[] = $id;
            } catch (\Throwable $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }
}
