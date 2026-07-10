<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Unit\Modules\Portal\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Repositories\FieldValueRepository;
use AssociationManager\Core\Fields\Services\FieldValidator;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Repositories\CertificateRepository;
use AssociationManager\Modules\Certificates\Repositories\CertificateTemplateRepository;
use AssociationManager\Modules\Certificates\Services\CertificateGenerator;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Repositories\DocumentRepository;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Domain\MemberStatusRegistry;
use AssociationManager\Modules\Members\Domain\MembershipPlanRegistry;
use AssociationManager\Modules\Members\Repositories\MemberRepository;
use AssociationManager\Modules\Members\Repositories\MemberStatusHistoryRepository;
use AssociationManager\Modules\Members\Repositories\MembershipRenewalRepository;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepository;
use AssociationManager\Modules\Notifications\Services\NotificationDispatcher;
use AssociationManager\Modules\Notifications\Services\NotificationService;
use AssociationManager\Modules\Portal\Services\PortalService;
use AssociationManager\Tests\Support\TestCase;

final class PortalServiceTest extends TestCase
{
    private MemberRepository $members;
    private MemberService $memberService;
    private DocumentRepository $documentRepository;
    private CertificateRepository $certificateRepository;
    private NotificationQueueRepository $notificationQueue;
    private NotificationTemplateRepository $notificationTemplates;
    private NotificationDispatcher $dispatcher;
    private FieldRegistry $fieldRegistry;
    private FieldValueRepository $fieldValueRepository;
    private FieldValueService $fieldValueService;
    private PortalService $service;

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
        $this->documentRepository = new DocumentRepository();
        $this->certificateRepository = new CertificateRepository();
        $this->notificationQueue = new NotificationQueueRepository();
        $this->notificationTemplates = new NotificationTemplateRepository();

        $this->dispatcher = new NotificationDispatcher(
            $this->notificationTemplates,
            $this->notificationQueue,
            new TemplateRenderer(),
        );

        $this->fieldRegistry = new FieldRegistry();
        $this->fieldValueRepository = new FieldValueRepository();
        $this->fieldValueService = new FieldValueService(
            $this->fieldRegistry,
            $this->fieldValueRepository,
            new FieldValidator(),
        );

        $this->service = new PortalService(
            $this->memberService,
            new DocumentService($this->documentRepository),
            new CertificateService(
                new CertificateTemplateRepository(),
                $this->certificateRepository,
                new CertificateGenerator(new TemplateRenderer()),
            ),
            new NotificationService($this->notificationQueue),
            $this->fieldRegistry,
            $this->fieldValueService,
            $this->fieldValueRepository,
        );

        $this->setNow('2026-01-01 00:00:00');
    }

    public function testMemberForReturnsTheLinkedMember(): void
    {
        $id = $this->members->insert(Member::draft(42, 'individual', 'jane@example.test'));

        $member = $this->service->memberFor(42);

        $this->assertNotNull($member);
        $this->assertSame($id, $member->id);
    }

    public function testMemberForReturnsFullProfileDataForTheDashboardAndProfileViews(): void
    {
        $this->members->insert(Member::draft(42, 'individual', 'jane@example.test'));

        $member = $this->service->memberFor(42);

        // Pins down that the Portal's Profile/Status/Summary sections get
        // real, fully-hydrated data - not just a bare id - since they read
        // these fields directly off the Member returned here.
        $this->assertNotNull($member);
        $this->assertSame('jane@example.test', $member->email);
        $this->assertSame('individual', $member->membershipType);
        $this->assertSame('candidate', $member->status);
    }

    public function testMemberForReturnsNullWhenNoAccountIsLinked(): void
    {
        $this->assertNull($this->service->memberFor(999));
    }

    public function testVisibleDocumentsExcludesAdminOnlyDocuments(): void
    {
        $this->documentRepository->insert(Document::draft('Public', null, null, 1, Visibility::VISIBILITY_PUBLIC, null));
        $this->documentRepository->insert(Document::draft('Private', null, null, 2, Visibility::VISIBILITY_PRIVATE, null));
        $this->documentRepository->insert(Document::draft('Admin', null, null, 3, Visibility::VISIBILITY_ADMIN, null));

        $visible = $this->service->visibleDocuments();

        $this->assertCount(2, $visible);
    }

    public function testCertificatesForReturnsOnlyThatMembersCertificates(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $this->certificateRepository->insert(Certificate::draft($id, 'membership', 1, '2026-01-01 00:00:00', null)->issue('2026-01-01 00:00:00'));
        $this->certificateRepository->insert(Certificate::draft(999, 'membership', 2, '2026-01-01 00:00:00', null)->issue('2026-01-01 00:00:00'));

        $certificates = $this->service->certificatesFor($member);

        $this->assertCount(1, $certificates);
        $this->assertSame($id, $certificates[0]->memberId);
    }

    public function testCertificatesForExcludesDraftCertificates(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $this->certificateRepository->insert(Certificate::draft($id, 'membership', 1, '2026-01-01 00:00:00', null));

        $this->assertCount(0, $this->service->certificatesFor($member));
    }

    public function testNotificationsForReturnsHistoryForTheMembersResolvedEmail(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual', 'jane@example.test'));
        $member = $this->members->find($id);

        $this->notificationTemplates->save(new NotificationTemplate(null, 'member_activated', NotificationChannel::EMAIL, 'Welcome', 'Body'));
        $this->dispatcher->notify('member_activated', 'jane@example.test', []);

        $notifications = $this->service->notificationsFor($member);

        $this->assertCount(1, $notifications);
        $this->assertSame('jane@example.test', $notifications[0]->recipient);
    }

    public function testNotificationsForReturnsEmptyWhenMemberHasNoResolvableEmail(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $this->assertSame([], $this->service->notificationsFor($member));
    }

    public function testMarkNotificationsReadForMarksSentNotificationsAsRead(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual', 'jane@example.test'));
        $member = $this->members->find($id);

        $this->notificationTemplates->save(new NotificationTemplate(null, 'member_activated', NotificationChannel::EMAIL, 'Welcome', 'Body'));
        $this->dispatcher->notify('member_activated', 'jane@example.test', []);

        $queued = $this->notificationQueue->allForRecipient('jane@example.test')[0];
        $this->notificationQueue->update($queued->markSent('2026-01-01 00:00:00'));

        $this->service->markNotificationsReadFor($member);

        $updated = $this->notificationQueue->allForRecipient('jane@example.test')[0];
        $this->assertSame('read', $updated->status);
    }

    public function testCustomFieldsForExcludesAdminOnlyFields(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'internal_note',
            label: 'Internal note',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_ADMIN,
        ));
        $this->fieldValueService->save('member', $id, ['specialty' => 'Cardiology', 'internal_note' => 'flagged']);

        $rows = $this->service->customFieldsFor($member);

        $this->assertCount(1, $rows);
        $this->assertSame('specialty', $rows[0]['field']->key);
        $this->assertSame('Cardiology', $rows[0]['value']);
    }

    public function testCustomFieldsForReturnsNullValueWhenNotSet(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));

        $rows = $this->service->customFieldsFor($member);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['value']);
    }

    public function testCanEditProfileIsTrueForCandidateAndPendingApproval(): void
    {
        $candidateId = $this->members->insert(Member::draft(null, 'individual'));
        $candidate = $this->members->find($candidateId);
        $this->assertTrue($this->service->canEditProfile($candidate));

        $pending = $this->memberService->transitionStatus($candidateId, MemberStatus::PENDING_APPROVAL);
        $this->assertTrue($this->service->canEditProfile($pending));
    }

    public function testCanEditProfileIsFalseOnceActive(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $active = $this->memberService->activateMember($id);

        $this->assertFalse($this->service->canEditProfile($active));
    }

    public function testSubmitForApprovalSavesFieldsAndTransitionsFromCandidate(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));

        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $updated = $this->service->submitForApproval($member, ['specialty' => 'Cardiology']);

        $this->assertSame(MemberStatus::PENDING_APPROVAL, $updated->status);
        $values = $this->fieldValueService->valuesFor('member', $id);
        $this->assertSame('Cardiology', $values['specialty']);
    }

    public function testSubmitForApprovalFiresTheStatusChangedHookOnlyOnFirstSubmission(): void
    {
        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $pending = $this->service->submitForApproval($member, []);
        $this->assertCount(1, $this->firedActionsNamed('association_manager_member_status_changed'));

        // Re-saving while still pending must not attempt a same-status
        // transition (transitionStatus() would throw \LogicException).
        $this->service->submitForApproval($pending, []);
        $this->assertCount(1, $this->firedActionsNamed('association_manager_member_status_changed'));
    }

    public function testEditableFileFieldsForOnlyReturnsFileTypeFields(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'specialty',
            label: 'Specialty',
            type: FieldDefinition::TYPE_TEXT,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));

        $id = $this->members->insert(Member::draft(null, 'individual'));
        $member = $this->members->find($id);

        $rows = $this->service->editableFileFieldsFor($member);

        $this->assertCount(1, $rows);
        $this->assertSame('id_document', $rows[0]['field']->key);
    }

    public function testEditableFileFieldsForIsAvailableForAnActiveMemberUnlikeCanEditProfile(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
        ));

        $id = $this->members->insert(Member::draft(null, 'individual'));
        $active = $this->memberService->activateMember($id);

        $this->assertFalse($this->service->canEditProfile($active), 'the general profile lock must still apply');
        $this->assertCount(1, $this->service->editableFileFieldsFor($active), 'file fields must stay reachable regardless');
    }

    public function testEditableFileFieldsForReportsCurrentAndPendingValues(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            visibility: FieldDefinition::VISIBILITY_PRIVATE,
            requiresApprovalToChange: true,
        ));

        $id = $this->members->insert(Member::draft(null, 'individual'));
        $active = $this->memberService->activateMember($id);

        $this->fieldValueRepository->set('member', $id, 'id_document', '100');
        $this->fieldValueRepository->setPending('member', $id, 'id_document', '200');

        $rows = $this->service->editableFileFieldsFor($active);

        $this->assertSame('100', $rows[0]['value']);
        $this->assertSame('200', $rows[0]['pending']);
    }

    public function testUpdateFileFieldsRoutesAReplacementToPendingWhenTheFieldRequiresApproval(): void
    {
        $this->fieldRegistry->register('member', new FieldDefinition(
            key: 'id_document',
            label: 'ID Document',
            type: FieldDefinition::TYPE_FILE,
            requiresApprovalToChange: true,
        ));

        $id = $this->members->insert(Member::draft(null, 'individual'));
        $active = $this->memberService->activateMember($id);

        $GLOBALS['__am_test_media_upload_result'] = 100;
        $this->service->updateFileFields($active, $this->fileUpload('id_document', 'first.pdf'));

        $GLOBALS['__am_test_media_upload_result'] = 200;
        $pending = $this->service->updateFileFields($active, $this->fileUpload('id_document', 'replacement.pdf'));

        $this->assertSame(['id_document'], $pending);
        $this->assertSame('100', $this->fieldValueService->valuesFor('member', $id)['id_document'], 'the live value must stay the old file until approved');
    }

    /**
     * @return array{name: array<string, string>, type: array<string, string>, tmp_name: array<string, string>, error: array<string, int>, size: array<string, int>}
     */
    private function fileUpload(string $fieldKey, string $fileName): array
    {
        return [
            'name' => [$fieldKey => $fileName],
            'type' => [$fieldKey => 'application/pdf'],
            'tmp_name' => [$fieldKey => '/tmp/fake'],
            'error' => [$fieldKey => 0],
            'size' => [$fieldKey => 123],
        ];
    }
}
