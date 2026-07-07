# ADR 009: UUID as a secondary Member identifier

## Status

Accepted

## Context

Release 0.2 (Sprint 9) wants a stable, non-guessable public identifier for members - for the REST API, future certificate generation, and eventual cross-installation sync/export. The internal `id` (BIGINT auto-increment) is sequential and already used as the physical primary key that `Payments.member_id` and other future FKs reference.

## Decision

Add `uuid CHAR(36)` (unique-indexed) to `wp_am_members` as a **second identifier**, generated with WordPress core's own `wp_generate_uuid4()` - no custom UUID code. The internal `id` stays the primary key:

- All existing FKs (`Payments.member_id`) and repository lookups keep using the internal `id` unchanged.
- `MemberRepository::insert()` generates the `uuid` at insert time; a one-off migration (`004_extend_members_table`) backfills `uuid` for any pre-existing rows.
- `uuid` is exposed in the REST payload (`MembersController::toArray()`); `id` still is too, for now, since existing internal tooling (Admin, Payments FK) depends on it.

We explicitly rejected replacing the PK with a UUID: it would force `Payments.member_id` (and any future FK) to change type, and UUID-as-clustered-PK causes worse `INSERT` locality/fragmentation in MySQL/InnoDB than plain sequential BIGINT.

## Consequences

Positive:

- No schema/FK churn elsewhere in the codebase - this is purely additive.
- External-facing identifiers (API, future certificates) don't leak sequential row counts.
- `wp_generate_uuid4()` means zero new dependency or hand-rolled UUID logic.

Negative:

- Two identifiers per member now exist (`id` internal, `uuid` external) - callers must be deliberate about which one a given surface should use; nothing enforces "REST always uses uuid, internal joins always use id" beyond convention and code review.
- The backfill migration does a per-row `UPDATE` in a PHP loop rather than a single bulk statement - fine at foundation-stage row counts, would need revisiting at very large existing datasets.
