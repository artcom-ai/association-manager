<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class FieldDefinition {

    public const TYPE_TEXT     = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER   = 'number';
    public const TYPE_DATE     = 'date';
    public const TYPE_SELECT   = 'select';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_FILE     = 'file';
    public const TYPE_LOCATION = 'location';

    public const VISIBILITY_PUBLIC  = 'public';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_ADMIN   = 'admin';

    private const VISIBILITY_RANK = [
        self::VISIBILITY_PUBLIC  => 0,
        self::VISIBILITY_PRIVATE => 1,
        self::VISIBILITY_ADMIN   => 2,
    ];

    /**
     * @param array<string, string>|null $options value => label, only for TYPE_SELECT
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $required = false,
        public readonly ?array $options = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?float $minValue = null,
        public readonly ?float $maxValue = null,
        public readonly ?string $helpText = null,
        public readonly int $order = 0,
        public readonly string $visibility = self::VISIBILITY_ADMIN,
    ) {
    }

    /**
     * Whether a viewer cleared for $viewerLevel ("public"/"private"/
     * "admin") may see this field - a field is visible if its own
     * visibility rank is no more restrictive than the viewer's.
     */
    public function isVisibleTo( string $viewerLevel ): bool {
        $fieldRank  = self::VISIBILITY_RANK[ $this->visibility ] ?? self::VISIBILITY_RANK[ self::VISIBILITY_ADMIN ];
        $viewerRank = self::VISIBILITY_RANK[ $viewerLevel ] ?? self::VISIBILITY_RANK[ self::VISIBILITY_PUBLIC ];

        return $fieldRank <= $viewerRank;
    }
}
