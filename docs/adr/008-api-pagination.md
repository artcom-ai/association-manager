# ADR 008: Pagination for REST list endpoints

## Status

Accepted

## Context

Sprint 8 ("API") was scoped, on request, to pagination: every list-returning REST endpoint currently returns every row with no limit, which will not scale as members/payments/events grow. Four modules have list endpoints: Members, Payments, Events (two lists: admin `all`, public `upcoming`), and Directory.

## Decision

A shared, module-agnostic pair of value objects in `AssociationManager\Core\Pagination`:

- `PaginationParams` (`page`, `perPage`, default 20, capped at 100, both clamped to >= 1) with `PaginationParams::fromQuery($page, $perPage)` to build one from raw REST request params, and `limit()`/`offset()` for SQL.
- `PaginatedResult` (`items`, `total`, `page`, `perPage`) with `totalPages()` and `toResponseArray()` producing `{"data": [...], "meta": {"page", "per_page", "total", "total_pages"}}`.

Every `*RepositoryInterface::paginate(PaginationParams): PaginatedResult` runs a `COUNT(*)` plus a `LIMIT/OFFSET` `SELECT`, both through `$wpdb->prepare()`. Directory needed its own filtered variant, `MemberRepositoryInterface::paginateByStatus(string $status, PaginationParams)`, since its "public entries" are a filtered subset (active members) of the Members table it doesn't own - reusing plain `paginate()` and filtering in PHP after the fact would make `total`/`total_pages` wrong (they'd count all members, not just active ones). `DirectoryService::paginate()` then re-maps the `PaginatedResult<Member>` to the same public-safe allow-listed shape `listPublicEntries()` already used, wrapping it in a new `PaginatedResult` with the same `total`/`page`/`perPage`. Events similarly got `paginateUpcomingPublished()` alongside `paginate()`, for the same reason (`upcoming` filters by status + start time).

**Scope boundary**: only REST list endpoints were paginated (`GET /members`, `/payments`, `/events`, `/events/upcoming`, `/directory`). The wp-admin HTML table screens (`MembersPage`, `PaymentsPage`, `EventsPage`) still call the original unpaginated `all()` / `listPublicEntries()` methods - paginating the admin UI tables was explicitly out of scope for this round, since Sprint 8 was framed as "API," not admin UX.

## Consequences

Positive:

- One reusable pagination primitive shared by all four modules rather than four bespoke implementations; a fifth module's list endpoint is a `paginate()` method + a controller changing `$this->service->all()` to `PaginationParams::fromQuery(...)` + `$this->service->paginate($params)`, not a new design.
- `page`/`per_page` are clamped defensively in `PaginationParams`'s constructor itself, so a malicious or malformed `per_page=999999999` can't force an unbounded query - every call site gets this for free.

Negative:

- Response shape changed for every list endpoint (bare array -> `{data, meta}` envelope) - this is a breaking change for any existing REST client of `GET /members`, `/payments`, `/events`, `/events/upcoming`, `/directory`. Acceptable now since this is still pre-1.0 foundation work with (to our knowledge) no external consumers yet, but must be called out clearly if this ships to an environment with real API clients.
- Admin HTML tables remain unpaginated; will need the same treatment once member/payment/event counts grow enough to matter for page load time.
- `COUNT(*)` runs as a second query on every paginated call; no total-count caching. Fine at current scale, worth revisiting if list endpoints become hot paths.
