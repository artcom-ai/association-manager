<?php

declare(strict_types=1);

namespace AssociationManager\Tests\Support;

/**
 * wp_send_json_success()/wp_send_json_error() call exit() in real
 * WordPress. The bootstrap stub throws this instead so tests can
 * catch it and inspect the JSON that was echoed.
 */
final class JsonResponseSent extends \Exception
{
}
