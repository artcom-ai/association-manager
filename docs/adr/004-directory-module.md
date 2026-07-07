# ADR 004: Directory module and cross-module dependencies

## Status

Accepted

## Context

Sprint 5 adds a public member directory: a REST endpoint and a shortcode, visible without login, listing active members. This is the first module that needs data owned by another module — Directory needs member data, but per ADR-002/003 Core stays module-agnostic and modules shouldn't reach into each other's internals.

## Decision

Directory depends on `MemberRepositoryInterface` (owned and registered by the Members module), not on `MemberRepository` or any Members internals. `DirectoryModule::register()` resolves it from the container: `$container->get(MemberRepositoryInterface::class)`.

This means module registration order matters for the first time: `Kernel::registerModules()` must register `MembersModule` before `DirectoryModule`, since `ModuleManager::boot()` runs every module's `register()` before any module's `boot()`, and Directory's `register()` needs Members' `register()` to have already put the interface in the container.

Directory itself only ever exposes public-safe data: `DirectoryService::listPublicEntries()` filters to `MemberStatus::ACTIVE` and returns a plain array with just `member_number`, `membership_type`, `joined_at` — it never returns `id`, `wp_user_id`, or `status` to the REST response or the shortcode template, since those are public-facing surfaces (no authentication required, per product decision).

## Consequences

Positive:

- Cross-module data access happens only through a published interface, never a concrete class or private repository internals — the dependency is explicit and swappable.
- Establishes that module registration order in `Kernel` is a real constraint, not incidental; future modules with dependencies must be ordered accordingly and should say so in a comment (see `Kernel::registerModules()`).
- Keeps the public/no-auth surface intentionally narrow (allow-list of fields) rather than serializing the full `Member` object and hoping nothing sensitive leaks.

Negative:

- Module registration order is now a manual, unenforced constraint — nothing fails loudly if a future module is registered before its dependency; a missing service would throw `RuntimeException: Service not found` at boot time, which is at least fail-fast but not caught earlier (e.g. by static analysis).
- No caching on `listPublicEntries()`; every REST/shortcode render re-queries all members. Acceptable at foundation stage, worth revisiting if directories grow large.
