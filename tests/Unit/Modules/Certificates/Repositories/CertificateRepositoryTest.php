<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Certificates\Repositories;

use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepository;
use AssociationManager\Tests\Support\TestCase;

final class CertificateRepositoryTest extends TestCase
{
    private CertificateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CertificateRepository();
    }

    public function testInsertAndFind(): void
    {
        $id = $this->repository->insert(
            new Certificate(null, 5, 'membership', 77, '2026-01-01 00:00:00', 9)
        );

        $found = $this->repository->find($id);

        $this->assertNotNull($found);
        $this->assertSame(5, $found->memberId);
        $this->assertSame(77, $found->wpAttachmentId);
    }

    public function testAllForMemberOnlyReturnsThatMembersCertificates(): void
    {
        $this->repository->insert(new Certificate(null, 5, 'membership', 1, '2026-01-01 00:00:00', null));
        $this->repository->insert(new Certificate(null, 5, 'membership', 2, '2026-01-02 00:00:00', null));
        $this->repository->insert(new Certificate(null, 6, 'membership', 3, '2026-01-01 00:00:00', null));

        $this->assertCount(2, $this->repository->allForMember(5));
        $this->assertCount(1, $this->repository->allForMember(6));
        $this->assertCount(0, $this->repository->allForMember(999));
    }
}
