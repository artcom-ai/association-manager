# ADR 012: Membership Engine (durations, renewals, grace, automatic expiry)

## Status

Accepted

## Context

Sprint 9's `renewMembership()` was explicitly minimal (ADR-010): it flips `expired -> active` and sets `expires_at` to whatever date the caller passes in - no concept of a plan's duration, no grace period, no automatic expiry, and (a gap noticed while implementing this sprint) no history at all when renewing an already-`active` member, since Sprint 9's audit trail only fires on status *changes*. Sprint 11 closes all of that.

## Decision

**`MembershipPlan` is a registry** (`Modules\Members\Domain\MembershipPlanRegistry`), the fourth occurrence of this project's "Core/module hosts an extension point, an implementation populates it" idiom (`AdminMenu` -> `MemberStatusRegistry` -> `Core\Fields\FieldRegistry` -> this). `Member::membershipType` (a plain string since Sprint 4) is now optionally a key into this registry: `{key, label, durationDays, gracePeriodDays}`. The registry starts **empty**, same as `FieldRegistry` and unlike `MemberStatusRegistry` - plan names/durations are entirely implementation-specific (ELESYTH's plans and a sports club's plans share nothing), so there's nothing universal for Members to seed.

**Grace period does not introduce a 7th status.** A member stays `active` throughout their plan's grace window; only `MembershipExpiryCalculator` needs to know about it (`cutoffFor()` = `expires_at` unchanged if no plan/0 grace, else pushed back by `gracePeriodDays`). Admin, REST, and Directory all keep treating a graced member exactly like any other active one - no new state to account for anywhere else in the codebase.

**Renewal has two paths now**, unified by a private `MemberService::applyRenewal()`:
- `renewMembership(memberId, string $newExpiresAt, ...)` - Sprint 9's manual/explicit-date path, unchanged signature, still available for admin override.
- `renewMembershipByPlan(memberId, ...)` (new) - resolves the member's plan, computes `new_expires_at = max(now, current expires_at) + plan.durationDays` (renewing before expiry extends from the current expiry, never loses remaining time; renewing after expiry extends from now), throws `\LogicException` (-> HTTP 409, same mapping already used for invalid transitions) if the member's `membershipType` has no registered plan.

Both paths now **always** write to a new `wp_am_membership_renewals` table (`member_id, plan_key, previous_expires_at, new_expires_at, renewed_by, renewed_at`) regardless of whether `status` changed - this is what actually satisfies "ιστορικό" (Sprint 9's `wp_am_member_status_history` only ever captured status transitions, so a same-status renewal - the common case of renewing before you've expired - previously left zero trace).

**Automatic expiry runs on WP-Cron**, scheduled daily. The coarse SQL filter (`MemberRepositoryInterface::findExpiredCandidates()`: `status = active AND expires_at < now`) is deliberately loose - it's a superset that includes members still inside their grace window. `MembershipExpiryRunner::run()` then applies `MembershipExpiryCalculator::hasExpired()` per candidate (the precise, grace-aware check) before actually calling `MemberService::expireMember()`. This keeps the SQL simple and keeps all grace-period business logic in one PHP class rather than trying to express varying per-plan grace windows in a single query.

The cron hook is scheduled defensively on `admin_init` (`if (!wp_next_scheduled(...))`), not only in `Activator::activate()` - identical reasoning to ADR-005's migration-timing fix: an already-active install that just receives this code update would otherwise never get the job scheduled without a deactivate/reactivate cycle. `Deactivator::deactivate()` clears it via `wp_clear_scheduled_hook()`.

**No new activation rules were added.** "Κανόνες ενεργοποίησης" is satisfied by what already exists: the transition-registry validation from ADR-010, plus this sprint's expiry rules. Coupling `activateMember()` to Payments (e.g. requiring a completed payment) was explicitly rejected for this sprint - that gap is already tracked in ADR-006 and stays there.

## Consequences

Positive:

- A future implementation defines its own plans (durations, grace periods) with zero Core/Members code changes - same guarantee already established for statuses (ADR-010) and custom fields (ADR-011).
- Renewal history is now complete regardless of whether a renewal happened to also change status - closes a real gap rather than papering over it.
- Automatic expiry actually happens without manual intervention, the stated point of "λήξεις."

Negative:

- `MemberService`'s constructor is now five dependencies deep (`repository, history, statuses, plans, renewals`). Still explicit and traceable, but a candidate for revisiting if a sixth concern shows up.
- The coarse-then-precise expiry filtering means every daily cron run loads every active-and-past-raw-expiry member (including ones still safely in grace) before filtering in PHP - fine at foundation-stage row counts, would need a smarter query (or per-plan SQL cutoffs) if membership counts grow very large.
- Still no coupling between Payments and Members activation - a member can be `active` without ever having a recorded completed payment. Unchanged limitation from ADR-006, reaffirmed here rather than solved.
