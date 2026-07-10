<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields;

use AssociationManager\Core\Visibility;

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

    public const VISIBILITY_PUBLIC  = Visibility::VISIBILITY_PUBLIC;
    public const VISIBILITY_PRIVATE = Visibility::VISIBILITY_PRIVATE;
    public const VISIBILITY_ADMIN   = Visibility::VISIBILITY_ADMIN;

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
        public readonly bool $showInList = false,
    ) {
    }

    /**
     * Whether a viewer cleared for $viewerLevel ("public"/"private"/
     * "admin") may see this field - a field is visible if its own
     * visibility rank is no more restrictive than the viewer's.
     */
    public function isVisibleTo( string $viewerLevel ): bool {
        return Visibility::isAtLeast( $this->visibility, $viewerLevel );
    }
}
