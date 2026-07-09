<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Directory\Services;

use AssociationManager\Core\Fields\FieldDefinition;
use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Services\FieldValueService;
use AssociationManager\Core\Pagination\PaginatedResult;
use AssociationManager\Core\Pagination\PaginationParams;
use AssociationManager\Modules\Members\Domain\Member;
use AssociationManager\Modules\Members\Domain\MemberSearchCriteria;
use AssociationManager\Modules\Members\Domain\MemberStatus;
use AssociationManager\Modules\Members\Repositories\MemberRepositoryInterface;

defined( 'ABSPATH' ) || exit;

final class DirectoryService {

    private const ENTITY_TYPE = 'member';

    public function __construct(
        private readonly MemberRepositoryInterface $members,
        private readonly FieldRegistry $fieldRegistry,
        private readonly FieldValueService $fieldValueService,
    ) {
    }

    /**
     * Public-safe, paginated directory entries (active members only). No
     * internal identifiers (id, wp_user_id, uuid) are ever exposed, at
     * any visibility level.
     *
     * @return PaginatedResult<array<string, mixed>>
     */
    public function paginate( PaginationParams $params, ?string $search = null ): PaginatedResult {
        return $this->paginateFor( FieldDefinition::VISIBILITY_PUBLIC, $params, $search );
    }

    /**
     * Same as paginate(), but for a logged-in viewer: adds status/
     * expires_at plus any custom field visible at "private" level
     * (which includes "public" ones too, per the visibility rank).
     *
     * @return PaginatedResult<array<string, mixed>>
     */
    public function paginatePrivate( PaginationParams $params, ?string $search = null ): PaginatedResult {
        return $this->paginateFor( FieldDefinition::VISIBILITY_PRIVATE, $params, $search );
    }

    /**
     * @return FieldDefinition[]
     */
    public function visibleCustomFields( string $viewerLevel ): array {
        return array_values(
            array_filter(
                $this->fieldRegistry->forEntityType( self::ENTITY_TYPE ),
                static fn ( FieldDefinition $field ): bool => $field->isVisibleTo( $viewerLevel )
            )
        );
    }

    /**
     * Map markers for every active member with a valid value in the
     * first registered TYPE_LOCATION field (single location field
     * supported). Loops every page of active members rather than
     * requesting one giant page, since PaginationParams caps per_page.
     *
     * @return array<int, array{label: string, lat: float, lng: float}>
     */
    public function mapPoints(): array {
        $locationField = $this->findLocationField();

        if ( $locationField === null ) {
            return [];
        }

        $points = [];
        $page   = 1;

        do {
            $result = $this->members->search(
                new MemberSearchCriteria( status: MemberStatus::ACTIVE ),
                new PaginationParams( $page, 100 )
            );

            foreach ( $result->items as $member ) {
                $value = $this->fieldValueService->valuesFor( self::ENTITY_TYPE, $member->requireId() )[ $locationField->key ] ?? null;

                if ( $value === null ) {
                    continue;
                }

                if ( ! preg_match( '/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $value, $matches ) ) {
                    continue;
                }

                $points[] = [
                    'label' => $member->memberNumber ?? '',
                    'lat'   => (float) $matches[1],
                    'lng'   => (float) $matches[2],
                ];
            }

            ++$page;
        } while ( $page <= $result->totalPages() );

        return $points;
    }

    /**
     * @return PaginatedResult<array<string, mixed>>
     */
    private function paginateFor( string $viewerLevel, PaginationParams $params, ?string $search ): PaginatedResult {
        $criteria = new MemberSearchCriteria( status: MemberStatus::ACTIVE, search: $search );
        $result   = $this->members->search( $criteria, $params );

        $entries = array_map(
            fn ( Member $member ): array => $this->buildEntry( $member, $viewerLevel ),
            $result->items
        );

        return new PaginatedResult( $entries, $result->total, $result->page, $result->perPage );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEntry( Member $member, string $viewerLevel ): array {
        $entry = [
            'member_number'   => $member->memberNumber,
            'membership_type' => $member->membershipType,
            'joined_at'       => $member->joinedAt,
        ];

        if ( $viewerLevel !== FieldDefinition::VISIBILITY_PUBLIC ) {
            $entry['status']     = $member->status;
            $entry['expires_at'] = $member->expiresAt;
        }

        $customValues = $this->fieldValueService->valuesFor( self::ENTITY_TYPE, $member->requireId() );

        foreach ( $this->visibleCustomFields( $viewerLevel ) as $field ) {
            $entry[ $field->key ] = $customValues[ $field->key ] ?? null;
        }

        return $entry;
    }

    private function findLocationField(): ?FieldDefinition {
        foreach ( $this->fieldRegistry->forEntityType( self::ENTITY_TYPE ) as $field ) {
            if ( $field->type === FieldDefinition::TYPE_LOCATION ) {
                return $field;
            }
        }

        return null;
    }
}
