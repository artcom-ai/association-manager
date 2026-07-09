<?php

declare(strict_types=1);

namespace AssociationManager\Modules\Notifications\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around wp_mail() - gives NotificationQueueRunner something
 * to depend on and test against instead of a global function call
 * directly. Deliberately not a MailTransportInterface (ADR-016 already
 * decided against that abstraction): this codebase has exactly one mail
 * transport (WordPress' own), so a swappable-transport interface would be
 * speculative generality with no second implementation in sight.
 */
final class EmailAdapter {

    public function send( string $to, string $subject, string $message ): bool {
        return wp_mail( $to, $subject, $message );
    }
}
