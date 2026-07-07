# ADR 005: Run pending migrations on admin_init, not only on activation

## Status

Accepted

## Context

`Activator::activate()` (ADR/migration system from Release 0.1) only runs on the WordPress `register_activation_hook`, i.e. the moment the plugin is activated. Adding the Payments module (Sprint 6) ships a second migration (`002_create_payments_table`), and revealed a gap: an install that is already active and simply receives a code update (new plugin files, e.g. via a new zip upload without deactivating first) would never run the new migration, because the activation hook does not fire again. The table would silently never be created.

## Decision

Extract the "load + run pending migrations" logic out of `Activator` into `AssociationManager\Database\Migrator::installPending()`, and call it from two places:

- `Activator::activate()` — unchanged behavior for fresh installs.
- `Kernel::registerHooks()`, hooked on `admin_init` — catches migrations that ship in a code-only update to an already-active install.

`MigrationRunner` was already idempotent (tracks completed migration IDs in the `association_manager_migrations` option and skips them), so calling `installPending()` on every admin request is safe; the cost per request is one `get_option()` call plus an array scan once no new migrations are pending.

## Consequences

Positive:

- New migrations in future releases actually get applied to already-active installs without requiring a manual deactivate/reactivate.
- No behavior change for fresh installs; activation path is untouched.

Negative:

- Runs on every wp-admin request rather than only when the plugin version actually changes; there's no version-gate short-circuit yet. Acceptable at this stage given the cost is a single option read; worth revisiting if migrations grow numerous or expensive.
- Still no `down()`/rollback path — unchanged from ADR-001 (database migrations); this ADR only changes *when* `up()` runs, not the model itself.
