<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields;

defined('ABSPATH') || exit;

final class FieldDefinition
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_SELECT = 'select';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_FILE = 'file';

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
    ) {
    }
}
