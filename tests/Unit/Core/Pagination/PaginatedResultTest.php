<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Pagination;

use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Tests\Support\TestCase;

final class PaginatedResultTest extends TestCase
{
    public function testTotalPagesRoundsUp(): void
    {
        $result = new PaginatedResult(['a', 'b'], total: 25, page: 1, perPage: 10);

        $this->assertSame(3, $result->totalPages());
    }

    public function testTotalPagesIsZeroWhenPerPageIsZero(): void
    {
        $result = new PaginatedResult([], total: 0, page: 1, perPage: 0);

        $this->assertSame(0, $result->totalPages());
    }

    public function testToResponseArrayShape(): void
    {
        $result = new PaginatedResult(['a'], total: 1, page: 1, perPage: 20);

        $response = $result->toResponseArray();

        $this->assertSame(['a'], $response['data']);
        $this->assertSame(1, $response['meta']['page']);
        $this->assertSame(20, $response['meta']['per_page']);
        $this->assertSame(1, $response['meta']['total']);
        $this->assertSame(1, $response['meta']['total_pages']);
    }
}
