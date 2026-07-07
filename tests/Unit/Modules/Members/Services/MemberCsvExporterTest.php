<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Members\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberCsvExporter;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Tests\Support\TestCase;

final class MemberCsvExporterTest extends TestCase
{
    private MemberService $service;
    private FieldRegistry $fieldRegistry;
    private FieldValueService $fieldValueService;
    private MemberCsvExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MemberService(
            new MemberRepository(),
            new MemberStatusHistoryRepository(),
            new MemberStatusRegistry(),
            new MembershipPlanRegistry(),
            new MembershipRenewalRepository(),
        );

        $this->fieldRegistry = new FieldRegistry();
        $this->fieldValueService = new FieldValueService(
            $this->fieldRegistry,
            new FieldValueRepository(),
            new FieldValidator(),
        );

        $this->exporter = new MemberCsvExporter($this->service, $this->fieldRegistry, $this->fieldValueService);
    }

    public function testHeadersIncludeRegisteredCustomFieldKeys(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
        ));

        $headers = $this->exporter->headers();

        $this->assertContains('specialty', $headers);
        $this->assertContains('member_number', $headers);
        $this->assertContains('status', $headers);
    }

    public function testRowsIncludeCoreAndCustomFieldValues(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
        ));

        $member = $this->service->createMember(1, 'individual');
        $this->service->activateMember($member->id);
        $this->fieldValueService->save('member', $member->id, ['specialty' => 'Ψυχολόγος']);

        $rows = iterator_to_array($this->exporter->rows());

        $this->assertCount(1, $rows);
        $this->assertSame('active', $rows[0]['status']);
        $this->assertSame('Ψυχολόγος', $rows[0]['specialty']);
    }
}
