<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Portal\Services;

use AssociationManager\Core\Visibility;
use AssociationManager\Modules\Certificates\Domain\Certificate;
use AssociationManager\Modules\Certificates\Services\CertificateService;
use AssociationManager\Modules\Documents\Domain\Document;
use AssociationManager\Modules\Documents\Services\DocumentService;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Thin aggregator - owns no domain data of its own, only composes read
 * access to Members/Documents/Certificates for the self-service portal
 * views. Depends on interfaces/services from those modules, same
 * cross-module dependency pattern as ADR-004 (Kernel::registerModules()
 * registers Members, Documents, and Certificates before Portal).
 */
final class PortalService {

    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly DocumentService $documents,
        private readonly CertificateService $certificates,
    ) {
    }

    public function memberFor( int $wpUserId ): ?Member {
        return $this->members->findByWpUserId( $wpUserId );
    }

    /**
     * Every logged-in member sees the same private-tier documents -
     * there's no per-member ACL beyond the 3-tier visibility scheme in
     * this pass, so this doesn't need $member, just that the caller has
     * already confirmed one exists (i.e. the viewer is a real member).
     *
     * @return Document[]
     */
    public function visibleDocuments(): array {
        return $this->documents->listVisibleTo( Visibility::VISIBILITY_PRIVATE );
    }

    /**
     * @return Certificate[]
     */
    public function certificatesFor( Member $member ): array {
        return $this->certificates->issuedForMember( $member->requireId() );
    }
}
