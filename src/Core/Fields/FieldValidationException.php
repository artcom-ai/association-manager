<?php

declare(strict_types=1);

namespace AssociationManager\Core\Fields;

defined( 'ABSPATH' ) || exit;

final class FieldValidationException extends \RuntimeException {

    /**
     * @param array<string, string[]> $errors field_key => error messages
     */
    public function __construct( private readonly array $errors ) {
        parent::__construct( 'Field validation failed.' );
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array {
        return $this->errors;
    }
}
