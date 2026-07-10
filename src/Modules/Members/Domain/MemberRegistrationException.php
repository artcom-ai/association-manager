<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Members\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Same shape as Core\Fields\FieldValidationException - field key => error
 * messages - so callers (the registration shortcode) can render errors
 * the same way the rest of this codebase already does.
 */
final class MemberRegistrationException extends \RuntimeException {

    /**
     * @param array<string, string[]> $errors field key => error messages
     */
    public function __construct( private readonly array $errors ) {
        parent::__construct( 'Member registration failed.' );
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array {
        return $this->errors;
    }
}
