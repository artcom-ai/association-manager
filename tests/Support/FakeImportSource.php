<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Support;

use AssociationManager\Modules\Importers\Domain\ImportRow;
use AssociationManager\Modules\Importers\ImportSourceInterface;

/**
 * A fixed set of rows, standing in for any real ImportSourceInterface
 * (MemberPressUserSource's own WP-glue - get_users()/get_user_meta() -
 * is untested here, same precedent as every other WP-glue class in this
 * suite) so MemberImportService's mapping/idempotency/conflict logic can
 * be tested without touching WordPress user functions at all.
 */
final class FakeImportSource implements ImportSourceInterface
{
    /**
     * @param ImportRow[] $rows
     */
    public function __construct(
        private readonly array $rows,
        private readonly string $sourceKey = 'testsource'
    ) {
    }

    public function key(): string
    {
        return $this->sourceKey;
    }

    public function label(): string
    {
        return 'Test Source';
    }

    /**
     * @return ImportRow[]
     */
    public function fetchRows(): array
    {
        return $this->rows;
    }
}
