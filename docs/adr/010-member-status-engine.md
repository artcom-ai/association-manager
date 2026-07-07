# ADR 010: Member status engine (registry-based, extensible)

## Status

Accepted

## Context

Sprint 4 shipped Member with three hardcoded statuses (`candidate/active/suspended`) and status logic baked directly into `Member::approve()`/`Member::suspend()`. Sprint 9 needs six built-in statuses (`candidate, active, inactive, suspended, expired, honorary`), enforced transition rules, an audit trail, and - critically - a way for a future per-client implementation (e.g. a sports-club or chamber installation) to add its own statuses without modifying Members or Core. This is the first time "implementation-specific extension of Core-owned business rules" has come up as a concrete requirement (distinct from ADR-004's module-to-module dependency, which was about data access, not extensible business vocabulary).

## Decision

**Registry pattern**, mirroring the existing `AdminMenu` mechanism (Core hosts a registry; anyone with container access registers into it):

- `MemberStatusRegistry` (`Modules\Members\Domain`) seeds the 6 built-in `StatusDefinition`s (`key, label, isActive, isTerminal`) and the built-in transition graph in its own constructor, and exposes `register(StatusDefinition)` / `allowTransition(from, to)` / `isTransitionAllowed(from, to): bool` / `get(key)`.
- `MembersModule::register()` puts one instance in the container. A future implementation module fetches `MemberStatusRegistry::class` from the container during its own `register()` and calls `register()`/`allowTransition()` to add e.g. an `on_leave` status - Members and Core never need to know it exists.
- Built-in transition graph: `candidate -> active|inactive`; `active -> suspended|expired|inactive|honorary`; `suspended -> active|inactive`; `expired -> active|inactive`; `honorary -> inactive`; `inactive -> active`.

**Domain stays pure**: `Member::withStatus(string $status, ?string $approvedAt = null)` and `withExpiresAt()` are plain data transformations with no WordPress calls (preserving the ADR-003 "zero WordPress dependency in Domain" rule) - `MemberService` (which is allowed WP dependencies) is what calls `current_time()` and passes timestamps in, and is also what consults `MemberStatusRegistry::isTransitionAllowed()` before persisting, throwing `\LogicException` on an invalid transition. This is the exact same 404-vs-409 pattern already established by Payments/Events (`\RuntimeException` -> 404 not found, `\LogicException` -> 409 invalid transition), applied consistently a third time.

**Audit trail is status-transitions only**: a new `wp_am_member_status_history` table (`member_id, from_status, to_status, changed_by, reason, changed_at`), written by `MemberService` on every transition (including the initial `null -> candidate` on creation). Full field-level diffing (name changes, membership-type changes, etc.) is explicitly out of scope - if that's needed later it's a separate, additive audit mechanism, not a replacement for this one.

**`renewMembership()` is intentionally minimal**: it only flips `expired -> active` (through the same registry check) and updates `expires_at`, firing `association_manager_member_renewed` alongside the usual status-changed event when the status actually changes. Grace periods, proration, and duration/history-driven expiry calculation are explicitly deferred to Sprint 11 ("Membership Engine") - building them now would duplicate work once that sprint's actual design lands.

**Breaking rename** (acceptable pre-1.0, same precedent as ADR-008): `MemberService::register()/approve()/suspend()` are replaced by `createMember()/activateMember()/suspendMember()/archiveMember()/renewMembership()`. REST route `/members/{id}/approve` becomes `/members/{id}/activate`; new routes `/suspend`, `/archive`, `/renew` were added. `MemberRepositoryInterface::paginateByStatus()` (Sprint 8) is replaced by a more general `search(MemberSearchCriteria, PaginationParams)` - `Directory` now calls `search(new MemberSearchCriteria(status: MemberStatus::ACTIVE), $params)` instead, one mechanism instead of two overlapping ones.

## Consequences

Positive:

- New statuses/transitions never require touching Members or Core - the exact extensibility the client asked for.
- Consistent 404/409 error semantics across all three lifecycle modules (Members, Payments, Events) rather than a bespoke pattern per module.
- Domain/Service separation is enforced a second time (Payments already did this for its own lifecycle), reinforcing it as the project's actual convention rather than a one-off.

Negative:

- Nothing stops a caller from bypassing `MemberService` and calling `MemberRepositoryInterface::update()` directly with an arbitrary status string - the registry only guards transitions that go through `changeStatus()`. This is a discipline/code-review concern, not a technical enforcement.
- `Directory`'s "active" filtering still checks a single literal status (`MemberStatus::ACTIVE`) via `search()`, not `StatusDefinition::isActive` across all registered statuses (including custom ones an implementation might flag as "active-like", e.g. an `honorary`-equivalent). Aggregating "all statuses where isActive=true" into Directory is a natural next step but was left out here to avoid scope creep on a module that already shipped (Sprint 5).
- `MemberStatusRegistry` state lives only in memory for the current request (rebuilt on every `MembersModule::register()` call) - there's no persistent/admin-editable status configuration; adding or changing statuses is still a code change (in Members or an implementation module), not a UI action.
