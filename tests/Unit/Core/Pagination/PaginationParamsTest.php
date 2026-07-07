<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Core\Pagination;

use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Tests\Support\TestCase;

final class PaginationParamsTest extends TestCase
{
    public function testDefaults(): void
    {
        $params = new PaginationParams();

        $this->assertSame(1, $params->page);
        $this->assertSame(20, $params->perPage);
    }

    public function testPageIsClampedToAtLeastOne(): void
    {
        $params = new PaginationParams(0, 20);

        $this->assertSame(1, $params->page);
    }

    public function testPerPageIsClampedToTheMaximum(): void
    {
        $params = new PaginationParams(1, 99999);

        $this->assertSame(100, $params->perPage);
    }

    public function testPerPageIsClampedToAtLeastOne(): void
    {
        $params = new PaginationParams(1, 0);

        $this->assertSame(1, $params->perPage);
    }

    public function testFromQueryParsesStrings(): void
    {
        $params = PaginationParams::fromQuery('3', '15');

        $this->assertSame(3, $params->page);
        $this->assertSame(15, $params->perPage);
    }

    public function testFromQueryDefaultsWhenNull(): void
    {
        $params = PaginationParams::fromQuery(null, null);

        $this->assertSame(1, $params->page);
        $this->assertSame(20, $params->perPage);
    }

    public function testLimitAndOffset(): void
    {
        $params = new PaginationParams(3, 10);

        $this->assertSame(10, $params->limit());
        $this->assertSame(20, $params->offset());
    }
}
