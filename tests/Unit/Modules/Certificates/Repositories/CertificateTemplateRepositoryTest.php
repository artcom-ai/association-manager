<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Certificates\Repositories;

use AssociationManager\Modules\Certificates\Domain\CertificateTemplate;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepository;
use AssociationManager\Tests\Support\TestCase;

final class CertificateTemplateRepositoryTest extends TestCase
{
    private CertificateTemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new CertificateTemplateRepository();
    }

    public function testSaveInsertsWhenNotExisting(): void
    {
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>'));

        $found = $this->repository->find('membership');
        $this->assertNotNull($found);
        $this->assertSame('Membership', $found->name);
    }

    public function testSaveUpdatesWhenAlreadyExisting(): void
    {
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership', '<p>x</p>'));
        $this->repository->save(new CertificateTemplate(null, 'membership', 'Membership v2', '<p>y</p>'));

        $all = $this->repository->all();
        $this->assertCount(1, $all);
        $this->assertSame('Membership v2', $all[0]->name);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->find('no-such-type'));
    }
}
