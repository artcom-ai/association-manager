<?php

declare(strict_types=1);

namespace AssociationManager\Core\Pagination;

defined( 'ABSPATH' ) || exit;

/**
 * @template T
 */
final class PaginatedResult {

    /**
     * @param array<int, T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function totalPages(): int {
        return $this->perPage > 0 ? (int) ceil( $this->total / $this->perPage ) : 0;
    }

    /**
     * @return array{data: array<int, T>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public function toResponseArray(): array {
        return [
            'data' => $this->items,
            'meta' => [
                'page'        => $this->page,
                'per_page'    => $this->perPage,
                'total'       => $this->total,
                'total_pages' => $this->totalPages(),
            ],
        ];
    }
}
