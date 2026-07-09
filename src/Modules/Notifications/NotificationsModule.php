<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications;

use AssociationManager\Core\Admin\AdminMenu;
use AssociationManager\Core\Container;
use AssociationManager\Core\ModuleInterface;
use AssociationManager\Core\Templating\TemplateRenderer;
use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Services\MemberService;
use AssociationManager\Modules\Notifications\Admin\NotificationTemplatesPage;
use AssociationManager\Modules\Notifications\Domain\NotificationChannel;
use AssociationManager\Modules\Notifications\Domain\NotificationTemplate;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepository;
use AssociationManager\Modules\Notifications\Repositories\NotificationQueueRepositoryInterface;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepository;
use AssociationManager\Modules\Notifications\Repositories\NotificationTemplateRepositoryInterface;
use AssociationManager\Modules\Notifications\Rest\NotificationsController;
use AssociationManager\Modules\Notifications\Services\EmailAdapter;
use AssociationManager\Modules\Notifications\Services\NotificationDispatcher;
use AssociationManager\Modules\Notifications\Services\NotificationQueueRunner;
use AssociationManager\Modules\Notifications\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Depends on Members' MemberService (not just its Repository interface -
 * resolveEmail()/findByWpUserId() are Service-layer concerns) to resolve
 * a member's contact email and to look up members by id - same
 * interface-dependency pattern as ADR-004, so Members must register()
 * before Notifications (see Kernel::registerModules()).
 */
final class NotificationsModule implements ModuleInterface {

    private const QUEUE_CRON_HOOK = 'association_manager_process_notification_queue';

    public function name(): string {
        return 'notifications';
    }

    public function register( Container $container ): void {
        $container->set( NotificationTemplateRepositoryInterface::class, new NotificationTemplateRepository() );
        $container->set( NotificationQueueRepositoryInterface::class, new NotificationQueueRepository() );
        $container->set( EmailAdapter::class, new EmailAdapter() );

        $container->set(
            NotificationDispatcher::class,
            new NotificationDispatcher(
                $container->get( NotificationTemplateRepositoryInterface::class ),
                $container->get( NotificationQueueRepositoryInterface::class ),
                $container->get( TemplateRenderer::class ),
            )
        );

        $container->set(
            NotificationQueueRunner::class,
            new NotificationQueueRunner(
                $container->get( NotificationQueueRepositoryInterface::class ),
                $container->get( EmailAdapter::class ),
            )
        );

        $container->set(
            NotificationService::class,
            new NotificationService( $container->get( NotificationQueueRepositoryInterface::class ) )
        );
    }

    public function boot( Container $container ): void {
        $dispatcher  = $container->get( NotificationDispatcher::class );
        $templates   = $container->get( NotificationTemplateRepositoryInterface::class );
        $queueRunner = $container->get( NotificationQueueRunner::class );
        $members     = $container->get( MemberService::class );

        $container->get( AdminMenu::class )->register( new NotificationTemplatesPage( $templates ) );

        add_action(
            'admin_post_association_manager_save_notification_template',
            function () use ( $templates ): void {
                $this->handleSaveNotificationTemplate( $templates );
            }
        );

        add_action(
            self::QUEUE_CRON_HOOK,
            static function () use ( $queueRunner ): void {
				$queueRunner->run();
			}
        );

        // Same defensive-scheduling reasoning as ADR-005/ADR-012: an
        // already-active install picking up this code update needs the
        // cron job scheduled without a deactivate/reactivate cycle.
        add_action(
            'admin_init',
            static function (): void {
				if ( ! wp_next_scheduled( self::QUEUE_CRON_HOOK ) ) {
					wp_schedule_event( time(), 'hourly', self::QUEUE_CRON_HOOK );
				}
			}
        );

        add_action(
            'association_manager_member_status_changed',
            function ( Member $member, ?string $oldStatus, string $newStatus ) use ( $dispatcher, $members ): void {
                $this->handleMemberStatusChanged( $dispatcher, $members, $member, $oldStatus, $newStatus );
            },
            10,
            3
        );

        add_action(
            'association_manager_member_renewed',
            function ( Member $member, string $newExpiresAt ) use ( $dispatcher, $members ): void {
                $dispatcher->notify(
                    'membership_renewed',
                    $members->resolveEmail( $member ),
                    [
                        'member_number' => $member->memberNumber ?? '',
                        'expires_at'    => $newExpiresAt,
                    ]
                );
            },
            10,
            2
        );

        add_action(
            'association_manager_document_published',
            function ( Document $document ) use ( $dispatcher ): void {
                $this->handleDocumentPublished( $dispatcher, $document );
            },
            10,
            1
        );

        add_action(
            'association_manager_certificate_issued',
            function ( Certificate $certificate ) use ( $dispatcher, $members ): void {
                $this->handleCertificateIssued( $dispatcher, $members, $certificate );
            },
            10,
            1
        );

        add_action(
            'rest_api_init',
            function () use ( $templates ): void {
				( new NotificationsController( $templates ) )->registerRoutes();
			}
        );
    }

    private function handleDocumentPublished( NotificationDispatcher $dispatcher, Document $document ): void {
        $dispatcher->notify(
            'document_published',
            get_option( 'admin_email' ),
            [
                'title'    => $document->title,
                'category' => $document->category ?? '',
            ]
        );
    }

    private function handleCertificateIssued(
        NotificationDispatcher $dispatcher,
        MemberService $members,
        Certificate $certificate
    ): void {
        $member = $members->find( $certificate->memberId );

        if ( $member === null ) {
            return;
        }

        $dispatcher->notify(
            'certificate_issued',
            $members->resolveEmail( $member ),
            [
                'member_number' => $member->memberNumber ?? '',
                'type_key'      => $certificate->typeKey,
                'issued_at'     => $certificate->issuedAt,
            ]
        );
    }

    private function handleMemberStatusChanged(
        NotificationDispatcher $dispatcher,
        MemberService $members,
        Member $member,
        ?string $oldStatus,
        string $newStatus
    ): void {
        $placeholders = [
            'member_number'   => $member->memberNumber ?? '',
            'membership_type' => $member->membershipType ?? '',
        ];

        if ( $oldStatus === null ) {
            $dispatcher->notify( 'admin_new_member', get_option( 'admin_email' ), $placeholders );

            return;
        }

        $eventKey = match ( $newStatus ) {
            MemberStatus::ACTIVE => 'member_activated',
            MemberStatus::SUSPENDED => 'member_suspended',
            MemberStatus::INACTIVE => 'member_archived',
            default => null,
        };

        if ( $eventKey === null ) {
            return;
        }

        $dispatcher->notify( $eventKey, $members->resolveEmail( $member ), $placeholders );
    }

    private function handleSaveNotificationTemplate( NotificationTemplateRepositoryInterface $templates ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'association-manager' ) );
        }

        $eventKey = isset( $_POST['event_key'] ) ? sanitize_text_field( (string) $_POST['event_key'] ) : '';

        check_admin_referer( 'association_manager_save_notification_template_' . $eventKey );

        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( (string) $_POST['subject'] ) : '';
        $body    = isset( $_POST['body'] ) ? sanitize_textarea_field( (string) $_POST['body'] ) : '';

        $existing = $templates->find( $eventKey, NotificationChannel::EMAIL );

        $template = $existing !== null
            ? $existing->withContent( $subject, $body )
            : new NotificationTemplate( null, $eventKey, NotificationChannel::EMAIL, $subject, $body );

        $templates->save( $template );

        wp_safe_redirect(
            add_query_arg(
                [
					'page'      => NotificationTemplatesPage::SLUG,
					'event_key' => $eventKey,
					'am_notice' => 'saved',
				],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
