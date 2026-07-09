<?php

declare(strict_types=1);

namespace AssociationManager\Core\Pagination;

defined( 'ABSPATH' ) || exit;

final class PaginationParams {

    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE     = 100;

    public readonly int $page;
    public readonly int $perPage;

    public function __construct( int $page = 1, int $perPage = self::DEFAULT_PER_PAGE ) {
        $this->page    = max( 1, $page );
        $this->perPage = min( self::MAX_PER_PAGE, max( 1, $perPage ) );
    }

    /**
     * Builds params from raw request query values (strings, ints, or null).
     */
    public static function fromQuery( mixed $page, mixed $perPage ): self {
        return new self(
            page: $page !== null ? (int) $page : 1,
            perPage: $perPage !== null ? (int) $perPage : self::DEFAULT_PER_PAGE,
        );
    }

    public function limit(): int {
        return $this->perPage;
    }

    public function offset(): int {
        return ( $this->page - 1 ) * $this->perPage;
    }
}
