<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Importers\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Modules\Importers\Domain\FieldMapping;
use AssociationManager\Modules\Importers\Domain\ImportRow;
use AssociationManager\Modules\Importers\Domain\ImportRowResult;
use AssociationManager\Modules\Importers\FieldMappingRegistry;
use AssociationManager\Modules\Importers\Services\MemberImportService;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Tests\Support\FakeImportSource;
use AssociationManager\Tests\Support\TestCase;

final class MemberImportServiceTest extends TestCase
{
    private const ENTITY_TYPE = 'member';

    private MemberRepository $members;
    private MemberService $memberService;
    private FieldRegistry $fieldRegistry;
    private FieldValueService $fieldValueService;
    private FieldMappingRegistry $fieldMappings;
    private MemberImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->members = new MemberRepository();
        $this->memberService = new MemberService(
            $this->members,
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

        // A real implementation (e.g. ELESYTH) always registers both the
        // FieldDefinition itself and the FieldMapping pointing at it -
        // mirrored here so this test reflects real usage.
        $this->fieldRegistry->register(self::ENTITY_TYPE, new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
        ));

        $this->fieldMappings = new FieldMappingRegistry();
        $this->fieldMappings->register('testsource', new FieldMapping('mepr-specialty', 'specialty'));

        $this->importService = new MemberImportService(
            $this->fieldMappings,
            $this->fieldRegistry,
            $this->fieldValueService,
            $this->members,
            $this->memberService,
        );

        $this->setNow('2026-01-01 00:00:00');
    }

    public function testDryRunDoesNotCreateAnyMember(): void
    {
        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);

        $summary = $this->importService->run($source, commit: false);

        $this->assertTrue($summary->dryRun);
        $this->assertSame(1, $summary->totalRows());
        $this->assertSame(1, $summary->createdCount());
        $this->assertSame(ImportRowResult::ACTION_CREATE, $summary->rows[0]->action);
        $this->assertCount(0, $this->members->all());
    }

    public function testDryRunReportsUnmappedSourceKeys(): void
    {
        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: [
                'mepr-specialty' => 'Cardiology',
                'mepr-unknown-thing' => 'whatever',
            ]),
        ]);

        $summary = $this->importService->run($source, commit: false);

        $this->assertSame(['mepr-unknown-thing'], $summary->allUnmappedKeys());
    }

    public function testDryRunReportsMissingRequiredFields(): void
    {
        $this->fieldRegistry->register(self::ENTITY_TYPE, new FieldDefinition(
            key: 'workplace',
            label: 'Workplace',
            type: FieldDefinition::TYPE_TEXT,
            required: true,
        ));

        $source = new FakeImportSource([
            // No mapping targets "workplace" at all, so it can never be filled.
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);

        $summary = $this->importService->run($source, commit: false);

        $this->assertSame(['workplace'], $summary->allMissingFields());
    }

    public function testCommitCreatesANewMemberWithMappedFieldValuesAndSourceTracking(): void
    {
        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);

        $summary = $this->importService->run($source, commit: true);

        $this->assertFalse($summary->dryRun);
        $this->assertSame(1, $summary->createdCount());

        $members = $this->members->all();
        $this->assertCount(1, $members);

        $member = $members[0];
        $this->assertSame('jane@example.test', $member->email);
        $this->assertSame(42, $member->wpUserId);
        $this->assertSame('testsource', $member->sourceSystem);
        $this->assertSame(42, $member->sourceUserId);
        $this->assertSame('2026-01-01 00:00:00', $member->importedAt);

        $values = $this->fieldValueService->valuesFor(self::ENTITY_TYPE, $member->requireId());
        $this->assertSame('Cardiology', $values['specialty']);
    }

    public function testCommitCarriesFirstAndLastNameThroughFromTheImportRow(): void
    {
        $source = new FakeImportSource([
            new ImportRow(
                sourceUserId: 42,
                wpUserId: 42,
                email: 'jane@example.test',
                rawFields: ['mepr-specialty' => 'Cardiology'],
                firstName: 'Jane',
                lastName: 'Doe',
            ),
        ]);

        $this->importService->run($source, commit: true);

        $member = $this->members->all()[0];
        $this->assertSame('Jane', $member->firstName);
        $this->assertSame('Doe', $member->lastName);
    }

    public function testReimportRefreshesNameWhenSourceProvidesIt(): void
    {
        $first = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology'], firstName: 'Jane', lastName: 'Doe'),
        ]);
        $this->importService->run($first, commit: true);

        $renamed = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology'], firstName: 'Janet', lastName: 'Doe'),
        ]);
        $this->importService->run($renamed, commit: true);

        $member = $this->members->all()[0];
        $this->assertSame('Janet', $member->firstName);
    }

    public function testRunningTheImporterTwiceDoesNotDuplicateMembers(): void
    {
        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);

        $this->importService->run($source, commit: true);
        $secondSummary = $this->importService->run($source, commit: true);

        $this->assertCount(1, $this->members->all());
        $this->assertSame(0, $secondSummary->createdCount());
        $this->assertSame(1, $secondSummary->updatedCount());
        $this->assertSame(ImportRowResult::ACTION_UPDATE, $secondSummary->rows[0]->action);
    }

    public function testCommitUpdatesAnExistingMemberFoundViaWpUserIdFallback(): void
    {
        // A member that already exists (e.g. created manually via the
        // admin "link WP account" control) and was never tagged with any
        // source - the importer must find it via wp_user_id and update
        // it, not create a duplicate.
        $existing = $this->memberService->createMember(wpUserId: 42, membershipType: 'individual');
        $this->assertNull($existing->sourceSystem);

        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);

        $summary = $this->importService->run($source, commit: true);

        $this->assertCount(1, $this->members->all());
        $this->assertSame(1, $summary->updatedCount());

        $updated = $this->members->find($existing->requireId());
        $this->assertNotNull($updated);
        $this->assertSame('testsource', $updated->sourceSystem);
        $this->assertSame(42, $updated->sourceUserId);
        $this->assertSame('jane@example.test', $updated->email);
    }

    public function testConflictsAreReportedWhenAReimportWouldChangeAnExistingValue(): void
    {
        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);
        $this->importService->run($source, commit: true);

        $changedSource = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Oncology']),
        ]);
        $summary = $this->importService->run($changedSource, commit: false);

        $conflictRows = $summary->rowsWithConflicts();
        $this->assertCount(1, $conflictRows);
        $this->assertSame(['specialty: "Cardiology" -> "Oncology"'], $conflictRows[0]->conflicts);
    }

    public function testNoConflictReportedWhenReimportingIdenticalValues(): void
    {
        $source = new FakeImportSource([
            new ImportRow(sourceUserId: 42, wpUserId: 42, email: 'jane@example.test', rawFields: ['mepr-specialty' => 'Cardiology']),
        ]);
        $this->importService->run($source, commit: true);

        $summary = $this->importService->run($source, commit: false);

        $this->assertSame([], $summary->rowsWithConflicts());
    }
}
