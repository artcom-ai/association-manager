# ADR 007: Events module

## Status

Accepted

## Context

Sprint 7 adds an event catalog: creating events, and moving them through a `draft -> published -> cancelled` lifecycle, following the same module structure as Members/Payments (ADR-003).

## Decision

- Same layered structure as prior modules, plus a `Public/` shortcode like Directory's, since events (unlike payments) are naturally public information.
- `Event::publish()` refuses to publish a `cancelled` event (`\LogicException`, mapped to HTTP 409 by the REST controller), mirroring the invalid-transition handling introduced for Payments (ADR-006). There is no unpublish; cancelling is the only reverse transition.
- Two read paths intentionally diverge: `EventService::all()` (admin, every status, ordered by start time) vs `EventService::upcomingPublished()` (`status = published AND starts_at >= now`, used by both the public REST route `GET /events/upcoming` and the `[association_manager_events]` shortcode). The public route has `permission_callback => '__return_true'`; every other `/events` route requires `manage_options`, same pattern as Directory (ADR-004) vs Members/Payments.
- The public surface (`upcoming()` in the REST controller, and the shortcode template) only exposes `title`, `description`, `location`, `starts_at`, `ends_at` — no `id`, `capacity`, or `status`, following Directory's precedent of an explicit allow-list rather than serializing the full domain object.

## Consequences

Positive:

- Reuses every established convention (module shape, lifecycle-via-domain-object, 404 vs 409 REST mapping, public allow-listed fields) rather than inventing new ones — Events required no new architectural decision beyond "should this be public," which was already answered by Directory.

Negative:

- **Out of scope for this sprint: member registration/RSVP for events.** There is no relationship between Members and Events yet (no attendee list, no capacity enforcement against registrations). `capacity` is stored but not enforced against anything. This is the next natural extension once a registrations sub-resource is designed — likely following the Directory precedent (Events module owning `EventRepositoryInterface`, a future Registrations concern depending on it plus `MemberRepositoryInterface`, the same cross-module pattern from ADR-004).
- No timezone handling beyond what WordPress' `current_time('mysql')` (site timezone) already provides; multi-timezone associations are not addressed.
