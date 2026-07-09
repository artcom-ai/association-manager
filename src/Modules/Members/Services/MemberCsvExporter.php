<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Services;

use AssociationManager\Core\Fields\FieldRegistry;
use AssociationManager\Core\Fields\Services\FieldValueService;

defined( 'ABSPATH' ) || exit;

final class MemberCsvExporter {

    private const ENTITY_TYPE = 'member';

    public function __construct(
        private readonly MemberService $memberService,
        private readonly FieldRegistry $fieldRegistry,
        private readonly FieldValueService $fieldValueService,
    ) {
    }

    /**
     * @return string[]
     */
    public function headers(): array {
        return [ ...$this->coreColumns(), ...$this->customColumnKeys() ];
    }

    /**
     * @return iterable<array<string, ?string>>
     */
    public function rows(): iterable {
        $customKeys = $this->customColumnKeys();

        foreach ( $this->memberService->all() as $member ) {
            $row = [
                'id'              => (string) $member->id,
                'uuid'            => $member->uuid,
                'member_number'   => $member->memberNumber,
                'email'           => $member->email,
                'status'          => $member->status,
                'membership_type' => $member->membershipType,
                'joined_at'       => $member->joinedAt,
                'expires_at'      => $member->expiresAt,
                'approved_at'     => $member->approvedAt,
            ];

            $customValues = $this->fieldValueService->valuesFor( self::ENTITY_TYPE, $member->requireId() );

            foreach ( $customKeys as $key ) {
                $row[ $key ] = $customValues[ $key ] ?? null;
            }

            yield $row;
        }
    }

    /**
     * @return string[]
     */
    private function coreColumns(): array {
        return [
            'id',
			'uuid',
			'member_number',
			'email',
			'status',
			'membership_type',
            'joined_at',
			'expires_at',
			'approved_at',
        ];
    }

    /**
     * @return string[]
     */
    private function customColumnKeys(): array {
        return array_map(
            static fn ( $field ): string => $field->key,
            $this->fieldRegistry->forEntityType( self::ENTITY_TYPE )
        );
    }
}
