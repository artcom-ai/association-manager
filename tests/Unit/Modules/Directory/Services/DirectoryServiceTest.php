<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Directory\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Directory\Services\DirectoryService;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Tests\Support\TestCase;

final class DirectoryServiceTest extends TestCase
{
    private MemberService $memberService;
    private FieldRegistry $fieldRegistry;
    private FieldValueService $fieldValueService;
    private DirectoryService $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $memberRepository = new MemberRepository();

        $this->memberService = new MemberService(
            $memberRepository,
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

        $this->directory = new DirectoryService($memberRepository, $this->fieldRegistry, $this->fieldValueService);
    }

    public function testPublicViewOnlyExposesPublicVisibilityFields(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'website',
            label: 'Website',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PUBLIC,
        ));
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'phone',
            label: 'Phone',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));

        $member = $this->memberService->createMember(1, 'individual');
        $this->memberService->activateMember($member->id);
        $this->fieldValueService->save('member', $member->id, ['website' => 'https://example.org', 'phone' => '12345']);

        $result = $this->directory->paginate(new PaginationParams(1, 10));
        $entry = $result->items[0];

        $this->assertArrayHasKey('website', $entry);
        $this->assertArrayNotHasKey('phone', $entry);
        $this->assertArrayNotHasKey('status', $entry);
        $this->assertArrayNotHasKey('expires_at', $entry);
        $this->assertArrayNotHasKey('id', $entry);
        $this->assertArrayNotHasKey('wp_user_id', $entry);
        $this->assertArrayNotHasKey('uuid', $entry);
    }

    public function testPrivateViewIncludesStatusExpiryAndPrivateFields(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'phone',
            label: 'Phone',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));

        $member = $this->memberService->createMember(1, 'individual');
        $this->memberService->activateMember($member->id);
        $this->fieldValueService->save('member', $member->id, ['phone' => '12345']);

        $result = $this->directory->paginatePrivate(new PaginationParams(1, 10));
        $entry = $result->items[0];

        $this->assertArrayHasKey('phone', $entry);
        $this->assertArrayHasKey('status', $entry);
        $this->assertArrayHasKey('expires_at', $entry);
        $this->assertArrayNotHasKey('id', $entry);
        $this->assertArrayNotHasKey('wp_user_id', $entry);
        $this->assertArrayNotHasKey('uuid', $entry);
    }

    public function testOnlyActiveMembersAppearInEitherView(): void
    {
        $candidate = $this->memberService->createMember(1, 'individual');

        $this->assertSame(0, $this->directory->paginate(new PaginationParams(1, 10))->total);
        $this->assertSame(0, $this->directory->paginatePrivate(new PaginationParams(1, 10))->total);
    }

    public function testSearchFiltersDirectoryResults(): void
    {
        $this->memberService->importRow('DIR-001', 'active', 'individual', null, null, null);
        $this->memberService->importRow('DIR-002', 'active', 'individual', null, null, null);

        $result = $this->directory->paginate(new PaginationParams(1, 10), 'DIR-001');

        $this->assertSame(1, $result->total);
    }

    public function testMapPointsSkipsMembersWithoutAValidLocation(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'location',
            label: 'Location',
            type: FieldDefinition::TYPE_LOCATION,
        ));

        $withLocation = $this->memberService->createMember(1, 'individual');
        $this->memberService->activateMember($withLocation->id);
        $this->fieldValueService->save('member', $withLocation->id, ['location' => '37.9838,23.7275']);

        $withoutLocation = $this->memberService->createMember(2, 'individual');
        $this->memberService->activateMember($withoutLocation->id);

        $points = $this->directory->mapPoints();

        $this->assertCount(1, $points);
        $this->assertEqualsWithDelta(37.9838, $points[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(23.7275, $points[0]['lng'], 0.0001);
    }

    public function testMapPointsReturnsEmptyArrayWhenNoLocationFieldRegistered(): void
    {
        $member = $this->memberService->createMember(1, 'individual');
        $this->memberService->activateMember($member->id);

        $this->assertSame([], $this->directory->mapPoints());
    }

    public function testMapPointsDoesNotTruncateAtOneHundred(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'location',
            label: 'Location',
            type: FieldDefinition::TYPE_LOCATION,
        ));

        for ($i = 0; $i < 105; $i++) {
            $member = $this->memberService->createMember($i, 'individual');
            $this->memberService->activateMember($member->id);
            $this->fieldValueService->save('member', $member->id, ['location' => '10.0,10.0']);
        }

        $this->assertCount(105, $this->directory->mapPoints());
    }
}
