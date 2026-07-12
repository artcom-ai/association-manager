<?php

declare(strict_types=1);

namespace AssociationManager\Database;

/**
 * Thrown when a write that a caller depends on for correctness
 * ($wpdb->insert()/update(), update_option()) reports failure. Letting
 * this propagate out of a MigrationInterface::up() is what keeps
 * MigrationRunner from ever recording that migration id as executed -
 * see MigrationRunner::run() and docs/adr/024-elesyth-implementation-migrations.md.
 */
final class DatabaseWriteException extends \RuntimeException {
}
